# Frontend Stack y Convenciones de UI

Este archivo describe el diseño del frontend, herramientas visuales y convenciones en **SAE**.

## Tecnologías Principales
1. **Livewire v4**: Reactividad server-side sin frameworks JS pesados.
2. **Livewire Volt v1**: Para componentes nuevos de archivo único (Single File Components - SFC).
3. **MaryUI**: Biblioteca de componentes Blade listos para usar (basados en DaisyUI).
4. **TailwindCSS v4**: Clases de utilidad para el estilado y maquetación de la aplicación.

---

## Directrices de Diseño y Componentes MaryUI

### 1. Clases y Colores (Tailwind v4)
- El usuario prefiere clases de **Tailwind v4**.
- Se deben evitar colores genéricos puros. Utilizar la paleta de temas de DaisyUI/MaryUI (ej. `bg-base-100`, `bg-base-200`, `text-primary`, `btn-primary`, `btn-outline`).

### 2. Componentes Comunes de MaryUI
Para mantener la coherencia estética del proyecto, prefiere siempre el uso de componentes MaryUI:
- **Botones**: `<x-button label="..." icon="..." class="btn-primary" />`
- **Selectores**:
  ```blade
  <x-select
      label="Seleccionar opción"
      wire:model.live="selectedId"
      :options="$options"
      option-value="id"
      option-label="name"
      placeholder="Elige una opción"
  />
  ```
- **Alertas y Megáfonos**: `<x-alert icon="o-megaphone" class="alert-warning">...</x-alert>`
- **Inputs y Áreas de Texto**: `<x-textarea placeholder="..." wire:model.defer="..." />`

---

## Reglas para Nuevos Componentes
- Cada vez que se cree un componente nuevo, este debe ser **un Componente Volt de Archivo Único (Volt SFC)** por preferencia explícita del usuario.
- El idioma de la interfaz del usuario debe ser siempre **Español**, mientras que el código (variables, funciones, base de datos) debe estar escrito en **Inglés**.
