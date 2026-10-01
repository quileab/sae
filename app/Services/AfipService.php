<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AfipService
{
    /**
     * Get an initialized Afip SDK instance.
     */
    public function getSdk(): \Afip
    {
        $config = DB::table('configs')->pluck('value', 'id');

        $taFolder = storage_path('app/afip/');
        if (! file_exists($taFolder)) {
            mkdir($taFolder, 0777, true);
        }

        $certName = $config['afip_cert'] ? 'certs/'.basename($config['afip_cert']) : null;
        $keyName = $config['afip_key'] ? 'certs/'.basename($config['afip_key']) : null;

        if (! $certName || ! $keyName) {
            throw new \Exception('Configuración de certificados AFIP incompleta.');
        }

        return new \Afip([
            'CUIT' => (int) preg_replace('/[^0-9]/', '', $config['cuit'] ?? '0'),
            'production' => ($config['production'] ?? 'false') == 'true',
            'cert' => $certName,
            'key' => $keyName,
            'res_folder' => storage_path('app/'),
            'ta_folder' => $taFolder,
            'exceptions' => true,
        ]);
    }

    /**
     * Get the last voucher number from AFIP.
     */
    public function getLastVoucher(int $ptoVta, int $cbteTipo): int
    {
        return $this->getSdk()->ElectronicBilling->GetLastVoucher($ptoVta, $cbteTipo);
    }

    /**
     * Create a new voucher in AFIP.
     */
    public function createVoucher(array $data): array
    {
        return $this->getSdk()->ElectronicBilling->CreateVoucher($data);
    }
}
