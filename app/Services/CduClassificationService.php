<?php

namespace App\Services;

use Illuminate\Support\Str;

class CduClassificationService
{
    /**
     * Tabla catálogo de la Clasificación Decimal Universal (CDU) adaptada a bibliotecas escolares.
     */
    protected array $catalog = [
        // 0 - Generalidades, Ciencia y Conocimiento, Informática
        ['code' => '004', 'name' => 'Informática, Programación y Computación', 'keywords' => 'computacion informatica software tecnologia internet redes python java'],
        ['code' => '030.1', 'name' => 'Enciclopedias y Diccionarios Generales', 'keywords' => 'enciclopedia diccionario referencia consulta generalidades'],
        ['code' => '070', 'name' => 'Periodismo, Prensa y Medios de Comunicación', 'keywords' => 'periodismo diario prensa revista noticia'],

        // 1 - Filosofía y Psicología
        ['code' => '100', 'name' => 'Filosofía General', 'keywords' => 'filosofia pensamiento etica moral'],
        ['code' => '159.9', 'name' => 'Psicología y Conducta Humana', 'keywords' => 'psicologia mente conducta emociones autoestima desarrollo'],

        // 2 - Religión y Mitología
        ['code' => '200', 'name' => 'Religión, Teología y Mitología', 'keywords' => 'religion teologia mitos mitologia biblia fe dios'],

        // 3 - Ciencias Sociales, Educación, Ciencias Políticas, Derecho
        ['code' => '300', 'name' => 'Ciencias Sociales en General', 'keywords' => 'ciencias sociales sociedad sociologia cultura'],
        ['code' => '316', 'name' => 'Sociología y Relaciones Humanas', 'keywords' => 'sociologia convivencia comunidad'],
        ['code' => '320', 'name' => 'Política y Gobierno', 'keywords' => 'politica democracia gobierno estado ciudadania'],
        ['code' => '330', 'name' => 'Economía y Finanzas', 'keywords' => 'economia finanzas comercio dinero trabajo'],
        ['code' => '37', 'name' => 'Educación, Enseñanza y Pedagogía', 'keywords' => 'educacion pedagogia didactica escuela docente planificaciones'],
        ['code' => '39', 'name' => 'Etnología, Folklore, Tradiciones y Costumbres', 'keywords' => 'folklore tradiciones costumbres mitos leyendas populares'],

        // 5 - Ciencias Exactas y Naturales
        ['code' => '500', 'name' => 'Ciencias Naturales y Exactas en General', 'keywords' => 'ciencias naturales naturaleza entorno'],
        ['code' => '51', 'name' => 'Matemáticas, Álgebra y Geometría', 'keywords' => 'matematica algebra geometria calculo numeros'],
        ['code' => '52', 'name' => 'Astronáutica, Astronomía y Espacio', 'keywords' => 'astronomia universo planetas estrellas espacio galaxias'],
        ['code' => '53', 'name' => 'Física y Energía', 'keywords' => 'fisica energia materia movimiento luz sonido'],
        ['code' => '54', 'name' => 'Química y Elementos', 'keywords' => 'quimica elementos laboratorio experimentos'],
        ['code' => '55', 'name' => 'Geología, Ciencias de la Tierra y Meteorología', 'keywords' => 'geologia tierra clima rocas volcanes terremotos meteorologia'],
        ['code' => '57', 'name' => 'Biología y Ciencias de la Vida', 'keywords' => 'biologia celula vida ecosistemas medio ambiente ecologia genetico'],
        ['code' => '58', 'name' => 'Botánica y Plantas', 'keywords' => 'botanica plantas flores arboles vegetales'],
        ['code' => '59', 'name' => 'Zoología y Animales', 'keywords' => 'zoologia animales fauna dinosaurios mamiferos insectos'],

        // 6 - Ciencias Aplicadas, Medicina, Tecnología
        ['code' => '61', 'name' => 'Medicina, Salud, Nutrición y Cuerpo Humano', 'keywords' => 'medicina salud cuerpo humano anatomia nutricion higiene'],
        ['code' => '62', 'name' => 'Ingeniería, Tecnología y Robótica', 'keywords' => 'tecnologia ingenieria robotica inventos maquinas'],
        ['code' => '63', 'name' => 'Agricultura, Ganadería y Naturaleza Aplicada', 'keywords' => 'agricultura huerta granja animales domesticos'],

        // 7 - Bellas Artes, Deportes, Espectáculos
        ['code' => '700', 'name' => 'Bellas Artes y Arte General', 'keywords' => 'arte pintura dibujo escultura arquitectura'],
        ['code' => '78', 'name' => 'Música y Canto', 'keywords' => 'musica instrumentos canciones canto'],
        ['code' => '796', 'name' => 'Deportes y Educación Física', 'keywords' => 'deporte futbol atletismo juego educacion fisica entrenamiento'],

        // 8 - Lingüística, Filología y Literatura
        ['code' => '800', 'name' => 'Lingüística, Idiomas y Gramática', 'keywords' => 'lengua gramatica ortografia linguistica idiomas ingles espanol'],
        ['code' => '82-1', 'name' => 'Poesía y Lírica', 'keywords' => 'poesia poemas versos lirica rimas'],
        ['code' => '82-2', 'name' => 'Teatro y Dramaturgia', 'keywords' => 'teatro obra dramatica guion actores obras de teatro'],
        ['code' => '82-3', 'name' => 'Literatura, Novelas y Cuentos (Narrativa)', 'keywords' => 'literatura novela cuento narrativa relato ficcion historias leyendas'],
        ['code' => '82-93', 'name' => 'Literatura Infantil y Primeros Lectores', 'keywords' => 'infantil cuentos ilustrados ninos primeros lectores album'],
        ['code' => '82-94', 'name' => 'Historietas, Cómics y Novela Gráfica', 'keywords' => 'historietas comics manga novela grafica ilustrada'],

        // 9 - Geografía, Biografías e Historia
        ['code' => '91', 'name' => 'Geografía General y Países', 'keywords' => 'geografia paises continentes mapa'],
        ['code' => '912', 'name' => 'Atlas, Cartografía y Mapas Temáticos', 'keywords' => 'atlas mapa cartografia mundi plano geofisico'],
        ['code' => '929', 'name' => 'Biografías y Memorias', 'keywords' => 'biografia vida memorias autobiografia personajes historicos praceres'],
        ['code' => '93/99', 'name' => 'Historia Universal y Nacional', 'keywords' => 'historia pasado guerras revoluciones civilizaciones historia argentina'],
    ];

    /**
     * Busca categorías CDU por código o término clave.
     *
     * @return array<int, array{code: string, name: string}>
     */
    public function search(string $query): array
    {
        if (trim($query) === '') {
            return array_slice($this->catalog, 0, 10);
        }

        $term = Str::ascii(strtolower(trim($query)));

        $matches = array_filter($this->catalog, function ($item) use ($term) {
            $codeMatch = str_contains(strtolower($item['code']), $term);
            $nameMatch = str_contains(Str::ascii(strtolower($item['name'])), $term);
            $keywordMatch = str_contains(Str::ascii(strtolower($item['keywords'])), $term);

            return $codeMatch || $nameMatch || $keywordMatch;
        });

        return array_values($matches);
    }

    /**
     * Sugiere una CDU apropiada según el tipo de obra.
     */
    public function suggestByWorkType(string $workType): string
    {
        return match ($workType) {
            'dictionary' => '030.1',
            'atlas' => '912',
            'children' => '82-93',
            'juvenile' => '82-3',
            'teacher' => '37',
            'periodical' => '82-94',
            'media' => '37',
            default => '82-3',
        };
    }
}
