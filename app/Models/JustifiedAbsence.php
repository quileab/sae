<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JustifiedAbsence extends Model
{
    public const ARTICLES = [
        '5 - a' => '5 - a: Enfermedad corta duración (con sueldo)',
        '5 - b' => '5 - b: Enfermedad corta duración (sin sueldo)',
        '7 - 0' => '7 - 0: Enfermedad larga duración (con sueldo)',
        '8 - a' => '8 - a: Accidente de trabajo',
        '8 - b' => '8 - b: Enfermedad profesional',
        '11 - 1' => '11 - 1: Tareas diferentes transitorias',
        '11 - 2' => '11 - 2: Tareas diferentes definitivas',
        '15 - a' => '15 - a: Preparto',
        '15 - b' => '15 - b: Maternidad',
        '16 - a' => '16 - a: Adopción de niños hasta 7 años',
        '16 - b' => '16 - b: Adopción de niños entre 7 y 12 años',
        '18' => '18: Franquicia por lactancia',
        '21 - 1' => '21 - 1: Matrimonio del agente',
        '21 - 2a' => '21 - 2a: Nacimiento de hijo de Agente varón',
        '21 - 2b' => '21 - 2b: Matrimonio de hijo',
        '21 - 3a' => '21 - 3a: Duelo por padres, hijos o cónyuge',
        '21 - 3b' => '21 - 3b: Duelo por hermanos',
        '21 - 3c' => '21 - 3c: Duelo por padres, hijos o hermanos políticos',
        '21 - 3d' => '21 - 3d: Duelo por abuelos, nietos, tíos, sobrinos y primos directos o políticos',
        '21 - 4a' => '21 - 4a: Atención a familiar enfermo (con sueldo)',
        '21 - 4b' => '21 - 4b: Atención a familiar enfermo (con sueldo)',
        '21 - 4c' => '21 - 4c: Atención a familiar enfermo (sin sueldo)',
        '23 - 1a' => '23 - 1a: Causa imprevista (con sueldo: 1 día al mes, hasta 4 días en el año)',
        '23 - 1b' => '23 - 1b: Causa imprevista (sin sueldo: hasta 15 días en el año)',
        '23 - 2' => '23 - 2: Fuerza mayor o caso fortuito',
        '23 - 3' => '23 - 3: Fenómeno meteorológico',
        '23 - 4' => '23 - 4: Donación de sangre',
        '24' => '24: Razones particulares',
        '25' => '25: Acompañar al cónyuge',
        '26 - a' => '26 - a: Rendir exámenes universitarios o terciarios',
        '26 - c' => '26 - c: Rendir exámenes de cursos que impliquen su elevamiento cultural',
        '27 - a' => '27 - a: Cursos obligatorios de ascenso a la docencia',
        '27 - b' => '27 - b: Cursos de perfeccionamiento docente determinados por el Ministerio de Educación',
        '28' => '28: Designación transitoria como miembro de tribunales o jurados',
        '29' => '29: Actividades de interés escolar',
        '30' => '30: Actividades escolares simultáneas',
        '31' => '31: Integración de mesas examinadoras',
        '36' => '36: Conducción de grupos, participación en tareas especiales. Acompañar delegación escolar',
        '38 - 1' => '38 - 1: Rendir prueba de oposición para ingreso o ascenso en la docencia',
        '38 - 2' => '38 - 2: Convocatoria a ofrecimiento de cargos',
        '38 - 3' => '38 - 3: Iniciar o completar carpeta médica',
        '41 - a' => '41 - a: Designación en cargos o comisiones de carácter transitorio',
        '41 - b' => '41 - b: Electos en cargos públicos de representación política',
        '41 - BIS a' => '41 - BIS a: Ascenso a cargos directivos o de supervisión en el sistema educativo (en forma transitoria)',
        '41 - BIS b' => '41 - BIS b: Otras designaciones transitorias que signifiquen ascenso en la carrera docente',
        '42 - a' => '42 - a: Integración comisión gremial (con sueldo)',
        '42 - b' => '42 - b: Permiso gremial',
        '42 - c' => '42 - c: Integración comisión gremial (sin sueldo)',
        '60' => '60: Causas no previstas en el Reglamento',
        'other' => 'Otro (indique el artículo y su descripción)',
    ];

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'article',
        'custom_article',
        'description',
        'status',
        'certificate_received_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'certificate_received_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getArticleLabelAttribute(): string
    {
        if ($this->article === 'other') {
            return $this->custom_article ? "Otro: {$this->custom_article}" : 'Otro';
        }

        return self::ARTICLES[$this->article] ?? ($this->article ?? 'Sin artículo');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pendiente',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            default => ucfirst($this->status ?? 'Pendiente'),
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'badge-warning',
            'approved' => 'badge-success text-white',
            'rejected' => 'badge-error text-white',
            default => 'badge-ghost',
        };
    }
}
