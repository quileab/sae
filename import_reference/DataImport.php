<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class DataImport extends Command
{
    protected $signature = 'db:import {file=source.sql}';

    protected $description = 'Universal data importer with dynamic table detection and intelligent multi-file sequence';

    public function handle()
    {
        $fileName = $this->argument('file');
        $filePath = base_path($fileName);

        if (! File::exists($filePath)) {
            $this->error("✘ Archivo base no encontrado: $filePath");

            return 1;
        }

        $this->newLine();
        $this->info("🚀 Iniciando migración universal desde: $fileName");
        $this->newLine();

        if (! $this->confirm('⚠️  Esto puede sobrescribir datos en las tablas detectadas. ¿Continuar?', true)) {
            $this->info('Operación cancelada.');

            return 0;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $chunksProcessed = 0;
        $logisticsMigrated = 0;
        $errors = 0;
        $truncatedTables = [];

        $filesToProcess = [$filePath];
        $extraFiles = File::glob(base_path('source_*.sql'));
        if (! empty($extraFiles)) {
            sort($extraFiles);
            $filesToProcess = array_merge($filesToProcess, $extraFiles);
        }

        foreach ($filesToProcess as $currentFilePath) {
            $baseName = basename($currentFilePath);
            $isIncremental = (str_starts_with($baseName, 'source_'));

            $this->newLine();
            $this->warn("📄 Procesando: $baseName ".($isIncremental ? '[INCREMENTAL]' : '[BASE]'));
            $lastTableInFile = '';

            $handle = fopen($currentFilePath, 'r');
            if ($handle) {
                $buffer = '';
                $insideInsert = false;
                $currentTable = '';

                while (($line = fgets($handle)) !== false) {
                    $trimmedLine = trim($line);

                    if (! $insideInsert) {
                        if (stripos($trimmedLine, 'INSERT INTO') === 0) {
                            if (preg_match('/INSERT INTO `?(\w+)`?/i', $trimmedLine, $matches)) {
                                $sourceTable = $matches[1];
                                $destinationTable = ($sourceTable === 'guest_users') ? 'alt_users' : $sourceTable;

                                if (Schema::hasTable($destinationTable)) {
                                    $insideInsert = true;
                                    $currentTable = $destinationTable;
                                    $buffer = $line;

                                    // 1. Traducir nombre
                                    if ($sourceTable !== $destinationTable) {
                                        $buffer = preg_replace('/INSERT INTO `?'.$sourceTable.'`?/i', "INSERT INTO `$destinationTable`", $buffer);
                                    }

                                    // 2. Convertir a REPLACE INTO si es incremental para evitar errores de duplicado
                                    if ($isIncremental) {
                                        $buffer = preg_replace('/INSERT INTO/i', 'REPLACE INTO', $buffer);
                                    }

                                    // 3. Truncar SOLO si es el archivo BASE
                                    if (! $isIncremental && ! in_array($currentTable, $truncatedTables)) {
                                        DB::table($currentTable)->truncate();
                                        $truncatedTables[] = $currentTable;
                                    }

                                    if ($currentTable !== $lastTableInFile) {
                                        $this->newLine();
                                        $this->output->write("   ⚡ <comment>$currentTable</comment> ");
                                        $lastTableInFile = $currentTable;
                                    }
                                }
                            }
                        }
                    } else {
                        $buffer .= $line;
                    }

                    if ($insideInsert && str_ends_with($trimmedLine, ';')) {
                        // Aplicar transformaciones inteligentes
                        if ($currentTable === 'orders') {
                            $migrationResult = $this->migrateOrderLogistics($buffer);
                            $buffer = $migrationResult['cleanSql'];
                            $logisticsMigrated += $migrationResult['count'];
                        } elseif ($currentTable === 'alt_users') {
                            $buffer = $this->transformAltUsers($buffer);
                        } elseif ($currentTable === 'products') {
                            $buffer = $this->transformProducts($buffer);
                        } elseif ($currentTable === 'personal_access_tokens') {
                            $buffer = $this->filterAdminTokens($buffer);
                        }

                        try {
                            if (! empty(trim($buffer))) {
                                DB::unprepared($buffer);
                                $chunksProcessed++;
                                $this->output->write('<info>.</info>');
                            }
                        } catch (\Exception $e) {
                            $errors++;
                            $this->newLine();
                            $this->error("   ✘ Error en $currentTable: ".substr($e->getMessage(), 0, 150));
                        }

                        $buffer = '';
                        $insideInsert = false;
                    }
                }
                fclose($handle);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->newLine(2);
        $this->info('✨ Importación Finalizada.');
        $this->line("   🚚 Registros logística: <info>$logisticsMigrated</info>");
        $this->line("   ✅ Total bloques: <info>$chunksProcessed</info>");
        if ($errors > 0) {
            $this->line("   ⚠️  Errores: <error>$errors</error>");
        }
        $this->newLine();

        return 0;
    }

    private function migrateOrderLogistics(string $sql): array
    {
        $logisticsCount = 0;
        $hasColumnNames = preg_match('/(?:INSERT|REPLACE) INTO `?orders`?\s*\((.*?)\)\s*VALUES/is', $sql, $colMatches);

        $columnMap = [];
        if ($hasColumnNames) {
            $cols = array_map(function ($c) {
                return trim($c, " `\n\r\t");
            }, explode(',', $colMatches[1]));
            $columnMap = array_flip($cols);
        }

        if (preg_match('/(?:VALUES)\s*(.*);/is', $sql, $matches)) {
            $valuesSection = $matches[1];
            preg_match_all('/\((.*?)\)(?:,|$)/s', $valuesSection, $rows);

            $newOrderRows = [];
            foreach ($rows[1] as $row) {
                $parts = str_getcsv($row, ',', "'");

                $idxAddr = $columnMap['sending_address'] ?? 4;
                $idxCity = $columnMap['sending_city'] ?? 5;
                $idxName = $columnMap['contact_name'] ?? 6;
                $idxPhone = $columnMap['contact_number'] ?? 7;
                $idxId = $columnMap['id'] ?? 0;

                $orderId = $parts[$idxId] ?? null;
                $addr = $parts[$idxAddr] ?? null;
                $city = $parts[$idxCity] ?? null;
                $cName = $parts[$idxName] ?? null;
                $phone = $parts[$idxPhone] ?? null;

                if ($addr && trim($addr) !== 'NULL') {
                    $this->saveToShippingDetails($orderId, $addr, $city, $cName, $phone);
                    $logisticsCount++;
                }

                $toRemove = [$idxAddr, $idxCity, $idxName, $idxPhone];
                foreach ($toRemove as $idx) {
                    unset($parts[$idx]);
                }
                $newOrderRows[] = $this->rebuildRow(array_values($parts));
            }

            $verb = str_contains($sql, 'REPLACE') ? 'REPLACE' : 'INSERT';
            $header = "$verb INTO `orders` VALUES";
            if ($hasColumnNames) {
                $cleanCols = array_map(function ($c) {
                    return trim($c, " `\n\r\t");
                }, explode(',', $colMatches[1]));
                $toRemoveNames = ['sending_address', 'sending_city', 'contact_name', 'contact_number'];
                $finalCols = array_filter($cleanCols, function ($c) use ($toRemoveNames) {
                    return ! in_array($c, $toRemoveNames);
                });
                $header = "$verb INTO `orders` (`".implode('`, `', $finalCols).'`) VALUES';
            }

            return [
                'cleanSql' => "$header ".implode(',', $newOrderRows).';',
                'count' => $logisticsCount,
            ];
        }

        return ['cleanSql' => $sql, 'count' => 0];
    }

    private function transformAltUsers(string $sql): string
    {
        $verb = str_contains($sql, 'REPLACE') ? 'REPLACE' : 'INSERT';
        if (preg_match('/(?:INSERT|REPLACE) INTO `?alt_users`?(?:\s*\((.*?)\))?\s*VALUES\s*(.*);/is', $sql, $matches)) {
            $hasCols = ! empty($matches[1]);
            $valuesSection = $matches[2];
            preg_match_all('/\((.*?)\)(?:,|$)/s', $valuesSection, $rows);
            $newRows = [];
            foreach ($rows[1] as $row) {
                $parts = str_getcsv($row, ',', "'");
                if (count($parts) === 15) {
                    array_splice($parts, 9, 0, [null]);
                    $newRows[] = $this->rebuildRow($parts);
                } else {
                    $newRows[] = "($row)";
                }
            }

            $header = "$verb INTO `alt_users` VALUES";
            if ($hasCols) {
                $cols = array_map(function ($c) {
                    return trim($c, " `\n\r\t");
                }, explode(',', $matches[1]));
                if (count($cols) === 15) {
                    array_splice($cols, 9, 0, ['activation_token']);
                    $header = "$verb INTO `alt_users` (`".implode('`, `', $cols).'`) VALUES';
                } else {
                    $header = "$verb INTO `alt_users` (".$matches[1].') VALUES';
                }
            }

            return "$header ".implode(',', $newRows).';';
        }

        return $sql;
    }

    private function transformProducts(string $sql): string
    {
        $verb = str_contains($sql, 'REPLACE') ? 'REPLACE' : 'INSERT';
        if (preg_match('/(?:INSERT|REPLACE) INTO `?products`?(?:\s*\((.*?)\))?\s*VALUES\s*(.*);/is', $sql, $matches)) {
            $hasCols = ! empty($matches[1]);
            $valuesSection = $matches[2];
            preg_match_all('/\((.*?)\)(?:,|$)/s', $valuesSection, $rows);
            $newRows = [];
            foreach ($rows[1] as $row) {
                $parts = str_getcsv($row, ',', "'");
                if (count($parts) >= 31) {
                    array_splice($parts, 14, 0, [0, 0]);
                    $parts = array_slice($parts, 0, 33);
                    $newRows[] = $this->rebuildRow($parts);
                } else {
                    $newRows[] = "($row)";
                }
            }

            $header = "$verb INTO `products` VALUES";
            if ($hasCols) {
                $cols = array_map(function ($c) {
                    return trim($c, " `\n\r\t");
                }, explode(',', $matches[1]));
                if (count($cols) >= 31) {
                    array_splice($cols, 14, 0, ['bonus_threshold', 'bonus_amount']);
                    $cols = array_slice($cols, 0, 33);
                    $header = "$verb INTO `products` (`".implode('`, `', $cols).'`) VALUES';
                } else {
                    $header = "$verb INTO `products` (".$matches[1].') VALUES';
                }
            }

            return "$header ".implode(',', $newRows).';';
        }

        return $sql;
    }

    private function filterAdminTokens(string $sql): string
    {
        $verb = str_contains($sql, 'REPLACE') ? 'REPLACE' : 'INSERT';
        if (preg_match('/(?:INSERT|REPLACE) INTO `?personal_access_tokens`?(?:\s*\((.*?)\))?\s*VALUES\s*(.*);/is', $sql, $matches)) {
            $hasCols = ! empty($matches[1]);
            $colNames = $hasCols ? array_map(function ($c) {
                return trim($c, " `\n\r\t");
            }, explode(',', $matches[1])) : [];
            $colMap = array_flip($colNames);

            $valuesSection = $matches[2];
            preg_match_all('/\((.*?)\)(?:,|$)/s', $valuesSection, $rows);

            $newRows = [];
            foreach ($rows[1] as $row) {
                $parts = str_getcsv($row, ',', "'");
                $idIdx = $colMap['tokenable_id'] ?? 2;
                $typeIdx = $colMap['tokenable_type'] ?? 1;
                $tokenId = trim($parts[$idIdx] ?? '', " '\"\t\n\r");
                $tokenType = str_replace('\\\\', '\\', trim($parts[$typeIdx] ?? '', " '\"\t\n\r"));

                if ($tokenId == '1' && (str_contains($tokenType, 'Models\User') || $tokenType == 'User')) {
                    $newRows[] = $this->rebuildRow($parts);
                }
            }
            if (empty($newRows)) {
                return '';
            }
            $header = $hasCols ? "$verb INTO `personal_access_tokens` (".$matches[1].') VALUES' : "$verb INTO `personal_access_tokens` VALUES";

            return "$header ".implode(',', $newRows).';';
        }

        return $sql;
    }

    private function rebuildRow(array $parts): string
    {
        $sanitizedParts = array_map(function ($val) {
            $val = ($val !== null) ? trim($val) : null;
            if ($val === 'NULL' || $val === null || $val === '') {
                return 'NULL';
            }

            return "'".str_replace("'", "''", $val)."'";
        }, $parts);

        return '('.implode(',', $sanitizedParts).')';
    }

    private function saveToShippingDetails($orderId, $addr, $city, $cName, $phone)
    {
        if (! $orderId || trim($orderId) === 'NULL') {
            return;
        }
        $clean = function ($val) {
            if ($val === null) {
                return null;
            }
            $v = trim($val, " '\"\t\n\r");

            return (strtoupper($v) === 'NULL' || $v === '') ? null : $v;
        };
        DB::table('shipping_details')->insert([
            'order_id' => $clean($orderId), 'address' => $clean($addr), 'city' => $clean($city),
            'contact_name' => $clean($cName), 'phone' => $clean($phone),
            'shipping_status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
