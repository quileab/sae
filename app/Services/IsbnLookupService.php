<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IsbnLookupService
{
    /**
     * Normaliza un código ISBN (remueve guiones, espacios y convierte a ISBN-13 si es posible).
     */
    public function normalizeIsbn(string $rawIsbn): string
    {
        $clean = preg_replace('/[^0-9X]/i', '', strtoupper(trim($rawIsbn)));

        // Convertir ISBN-10 a ISBN-13 si tiene 10 dígitos
        if (strlen($clean) === 10) {
            $isbn9 = '978'.substr($clean, 0, 9);
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += (int) $isbn9[$i] * ($i % 2 === 0 ? 1 : 3);
            }
            $checkDigit = (10 - ($sum % 10)) % 10;

            return $isbn9.$checkDigit;
        }

        return $clean;
    }

    /**
     * Realiza la búsqueda de metadatos de un libro por ISBN utilizando estrategia en cascada (Fallbacks).
     *
     * @return array{
     *    success: bool,
     *    source: string,
     *    data: array{
     *        title?: string,
     *        author?: string,
     *        publisher?: string,
     *        extent?: int,
     *        edition?: string,
     *        synopsis?: string,
     *        gender?: string,
     *        digital?: string
     *    },
     *    message?: string
     * }
     */
    public function lookup(string $rawIsbn): array
    {
        $isbn = $this->normalizeIsbn($rawIsbn);

        if (empty($isbn)) {
            return [
                'success' => false,
                'source' => 'None',
                'data' => [],
                'message' => 'Por favor ingrese un ISBN válido (10 o 13 dígitos).',
            ];
        }

        // Intento 1: Open Library API (con User-Agent para evitar rate-limits)
        $openLib = $this->lookupOpenLibrary($isbn);
        if ($openLib['success']) {
            return $openLib;
        }

        // Intento 2: Google Books API (con formateo isbn:...)
        $google = $this->lookupGoogleBooks($isbn);
        if ($google['success']) {
            return $google;
        }

        // Intento 3: Open Library Search API (Fallback por texto completo)
        $openLibSearch = $this->lookupOpenLibrarySearch($isbn);
        if ($openLibSearch['success']) {
            return $openLibSearch;
        }

        return [
            'success' => false,
            'source' => 'None',
            'data' => [],
            'message' => "No se encontraron datos para el ISBN {$isbn} en los catálogos internacionales.",
        ];
    }

    /**
     * Búsqueda por Título y/o Autor en catálogos internacionales (Google Books & Open Library Search).
     *
     * @return array<int, array{title: string, author: string, publisher: string, edition: string, isbn: string, synopsis: string, extent: int, gender: string, digital: ?string, source: string}>
     */
    public function searchByTitleOrAuthor(string $query): array
    {
        $term = trim($query);

        if (strlen($term) < 3) {
            return [];
        }

        $results = [];

        // 1. Google Books Search
        try {
            $url = 'https://www.googleapis.com/books/v1/volumes?q='.urlencode($term).'&maxResults=6';
            $response = Http::timeout(4)->get($url);

            if ($response->successful() && isset($response->json()['items'])) {
                foreach ($response->json()['items'] as $item) {
                    $info = $item['volumeInfo'] ?? [];
                    $isbns = $info['industryIdentifiers'] ?? [];
                    $isbnStr = '';

                    foreach ($isbns as $identifier) {
                        if (in_array($identifier['type'] ?? '', ['ISBN_13', 'ISBN_10'])) {
                            $isbnStr = $identifier['identifier'];
                            break;
                        }
                    }

                    $synopsis = $info['description'] ?? '';
                    if (empty($synopsis)) {
                        $synopsis = $this->fetchWikipediaSynopsis($info['title'] ?? '');
                    }

                    $results[] = [
                        'title' => $info['title'] ?? '',
                        'author' => isset($info['authors']) ? implode(', ', $info['authors']) : '',
                        'publisher' => $info['publisher'] ?? '',
                        'edition' => isset($info['publishedDate']) ? (strlen($info['publishedDate']) === 4 ? "{$info['publishedDate']}-01-01" : $info['publishedDate']) : '',
                        'isbn' => $isbnStr,
                        'synopsis' => $synopsis,
                        'extent' => $info['pageCount'] ?? 0,
                        'gender' => isset($info['categories']) ? implode(', ', $info['categories']) : '',
                        'digital' => $item['accessInfo']['pdf']['downloadLink'] ?? null,
                        'source' => 'Google Books'.(! empty($synopsis) && empty($info['description'] ?? '') ? ' + Wikipedia' : ''),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('IsbnLookupService GoogleBooks Search Error: '.$e->getMessage());
        }

        // 2. Open Library Search Fallback
        if (count($results) < 3) {
            try {
                $url = 'https://openlibrary.org/search.json?q='.urlencode($term).'&limit=5';
                $response = Http::timeout(4)
                    ->withHeaders(['User-Agent' => 'SAE-School-Library-Management/1.0 (contact@quileab.com)'])
                    ->get($url);

                if ($response->successful() && ! empty($response->json()['docs'])) {
                    foreach ($response->json()['docs'] as $doc) {
                        $isbnStr = isset($doc['isbn']) && is_array($doc['isbn']) ? $doc['isbn'][0] : '';
                        $synopsis = isset($doc['first_sentence']) ? implode(' ', (array) $doc['first_sentence']) : '';

                        if (empty($synopsis)) {
                            $synopsis = $this->fetchWikipediaSynopsis($doc['title'] ?? '');
                        }

                        $results[] = [
                            'title' => $doc['title'] ?? '',
                            'author' => isset($doc['author_name']) ? implode(', ', (array) $doc['author_name']) : '',
                            'publisher' => isset($doc['publisher']) ? implode(', ', array_slice((array) $doc['publisher'], 0, 2)) : '',
                            'edition' => isset($doc['first_publish_year']) ? "{$doc['first_publish_year']}-01-01" : '',
                            'isbn' => $isbnStr,
                            'synopsis' => $synopsis,
                            'extent' => $doc['number_of_pages_median'] ?? 0,
                            'gender' => isset($doc['subject']) ? implode(', ', array_slice((array) $doc['subject'], 0, 3)) : '',
                            'digital' => null,
                            'source' => 'Open Library'.(! empty($synopsis) && ! isset($doc['first_sentence']) ? ' + Wikipedia' : ''),
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('IsbnLookupService OpenLibrary Search Error: '.$e->getMessage());
            }
        }

        return array_slice($results, 0, 8);
    }

    /**
     * Busca el resumen / sinopsis del libro en Wikipedia en español usando su API REST oficial.
     */
    public function fetchWikipediaSynopsis(string $title): string
    {
        $cleanTitle = trim($title);

        if (empty($cleanTitle)) {
            return '';
        }

        try {
            // Intentar primero con la API REST de Wikipedia en español
            $formattedTitle = rawurlencode(str_replace(' ', '_', $cleanTitle));
            $url = "https://es.wikipedia.org/api/rest_v1/page/summary/{$formattedTitle}";

            $response = Http::timeout(3)
                ->withHeaders([
                    'User-Agent' => 'SAE-School-Library-Management/1.0 (contact@quileab.com)',
                    'Accept' => 'application/json',
                ])
                ->get($url);

            if ($response->successful() && isset($response->json()['extract'])) {
                $extract = trim($response->json()['extract']);
                if (! empty($extract) && ! str_contains(strtolower($extract), 'puede referirse a')) {
                    return $extract;
                }
            }

            // Fallback con búsqueda de artículos por término
            $searchUrl = 'https://es.wikipedia.org/w/api.php?action=query&format=json&prop=extracts&exintro=1&explaintext=1&generator=search&gsrsearch='.urlencode($cleanTitle.' libro novela').'&gsrlimit=1';
            $searchResponse = Http::timeout(3)->get($searchUrl);

            if ($searchResponse->successful() && isset($searchResponse->json()['query']['pages'])) {
                $pages = $searchResponse->json()['query']['pages'];
                foreach ($pages as $page) {
                    if (isset($page['extract']) && ! empty(trim($page['extract']))) {
                        return trim($page['extract']);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('IsbnLookupService Wikipedia Synopsis Error: '.$e->getMessage());
        }

        return '';
    }

    /**
     * Consulta Open Library API (bibkeys method).
     */
    protected function lookupOpenLibrary(string $isbn): array
    {
        try {
            $url = "https://openlibrary.org/api/books?bibkeys=ISBN:{$isbn}&format=json&jscmd=data";
            $response = Http::timeout(4)
                ->withHeaders([
                    'User-Agent' => 'SAE-School-Library-Management/1.0 (contact@quileab.com)',
                    'Accept' => 'application/json',
                ])
                ->get($url);

            if ($response->successful()) {
                $json = $response->json();
                $key = "ISBN:{$isbn}";

                if (isset($json[$key])) {
                    $book = $json[$key];
                    $data = [
                        'title' => $book['title'] ?? '',
                        'author' => isset($book['authors']) ? collect($book['authors'])->pluck('name')->implode(', ') : '',
                        'publisher' => isset($book['publishers']) ? collect($book['publishers'])->pluck('name')->implode(', ') : '',
                        'extent' => $book['number_of_pages'] ?? 0,
                        'synopsis' => $book['notes'] ?? ($book['by_statement'] ?? ''),
                        'gender' => isset($book['subjects']) ? collect($book['subjects'])->pluck('name')->take(5)->implode(', ') : '',
                        'digital' => $book['url'] ?? null,
                    ];

                    if (isset($book['publish_date'])) {
                        try {
                            $data['edition'] = Carbon::parse($book['publish_date'])->format('Y-m-d');
                        } catch (\Exception $e) {
                            if (preg_match('/\b\d{4}\b/', $book['publish_date'], $matches)) {
                                $data['edition'] = "{$matches[0]}-01-01";
                            }
                        }
                    }

                    if (! empty($data['title'])) {
                        return [
                            'success' => true,
                            'source' => 'Open Library',
                            'data' => $data,
                            'message' => 'Datos obtenidos exitosamente desde Open Library.',
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('IsbnLookupService OpenLibrary Error: '.$e->getMessage());
        }

        return ['success' => false, 'source' => 'Open Library', 'data' => []];
    }

    /**
     * Consulta Google Books API.
     */
    protected function lookupGoogleBooks(string $isbn): array
    {
        try {
            $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$isbn}";
            $response = Http::timeout(4)->get($url);

            if ($response->successful() && isset($response->json()['items'][0])) {
                $item = $response->json()['items'][0];
                $volumeInfo = $item['volumeInfo'] ?? [];
                $accessInfo = $item['accessInfo'] ?? [];

                $data = [
                    'title' => $volumeInfo['title'] ?? '',
                    'author' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : '',
                    'publisher' => $volumeInfo['publisher'] ?? '',
                    'extent' => $volumeInfo['pageCount'] ?? 0,
                    'synopsis' => $volumeInfo['description'] ?? '',
                    'gender' => isset($volumeInfo['categories']) ? implode(', ', $volumeInfo['categories']) : '',
                    'digital' => $accessInfo['pdf']['downloadLink']
                        ?? $accessInfo['pdf']['webReaderLink']
                        ?? $volumeInfo['canonicalVolumeLink']
                        ?? null,
                ];

                if (isset($volumeInfo['publishedDate'])) {
                    $date = $volumeInfo['publishedDate'];
                    if (strlen($date) === 4) {
                        $data['edition'] = "{$date}-01-01";
                    } elseif (strlen($date) === 7) {
                        $data['edition'] = "{$date}-01";
                    } else {
                        $data['edition'] = $date;
                    }
                }

                if (! empty($data['title'])) {
                    return [
                        'success' => true,
                        'source' => 'Google Books',
                        'data' => $data,
                        'message' => 'Datos obtenidos exitosamente desde Google Books.',
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('IsbnLookupService GoogleBooks Error: '.$e->getMessage());
        }

        return ['success' => false, 'source' => 'Google Books', 'data' => []];
    }

    /**
     * Fallback secundario mediante Open Library Search API.
     */
    protected function lookupOpenLibrarySearch(string $isbn): array
    {
        try {
            $url = "https://openlibrary.org/search.json?q={$isbn}";
            $response = Http::timeout(4)->get($url);

            if ($response->successful() && ! empty($response->json()['docs'])) {
                $doc = $response->json()['docs'][0];

                $data = [
                    'title' => $doc['title'] ?? '',
                    'author' => isset($doc['author_name']) ? implode(', ', (array) $doc['author_name']) : '',
                    'publisher' => isset($doc['publisher']) ? implode(', ', array_slice((array) $doc['publisher'], 0, 2)) : '',
                    'extent' => $doc['number_of_pages_median'] ?? 0,
                    'synopsis' => isset($doc['first_sentence']) ? implode(' ', (array) $doc['first_sentence']) : '',
                    'gender' => isset($doc['subject']) ? implode(', ', array_slice((array) $doc['subject'], 0, 4)) : '',
                    'digital' => null,
                ];

                if (isset($doc['first_publish_year'])) {
                    $data['edition'] = "{$doc['first_publish_year']}-01-01";
                }

                if (! empty($data['title'])) {
                    return [
                        'success' => true,
                        'source' => 'Open Library Search',
                        'data' => $data,
                        'message' => 'Datos obtenidos exitosamente desde el buscador de Open Library.',
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('IsbnLookupService OpenLibrarySearch Error: '.$e->getMessage());
        }

        return ['success' => false, 'source' => 'Open Library Search', 'data' => []];
    }
}
