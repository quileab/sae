# SAE - School Administration Environment

SAE is a comprehensive, modern web application designed to manage all aspects of a school's administration. Built with the TALL stack (Tailwind CSS, Alpine.js, Laravel, and Livewire), it provides a reactive and efficient user experience for administrators, teachers, and students.

## Key Features

- **Academic Management:**
  - Manage careers, subjects, and modalities.
  - Handle student enrollments and inscriptions.
  - Track class sessions, attendance, and grades.

- **Financial Control:**
  - Create and manage payment plans and installments.
  - Record and track user payments with a detailed payment history.
  - Generate financial reports.

- **Communication & Collaboration:**
  - Integrated real-time chat for seamless communication.
  - Calendar and event system to keep track of important dates and school events.
  - Content management for subject materials and resources.

- **Reporting:**
  - Generate various reports, including:
    - Student report cards.
    - Class attendance records.
    - Statistical reports.

## Tech Stack

- **Backend:** Laravel 12
- **Frontend:** 
  - Livewire 4 & Volt
  - MaryUI Component Library
  - Tailwind CSS 4
  - Alpine.js
- **Database:** MySQL
- **Development Environment:** Vite

## Recent Changes

- **Simplified Content Fix:** Topics with resources but no description are now correctly visible in the student view. Previously, these were hidden if the content field was empty.

## Getting Started

### Prerequisites

- PHP 8.2+
- Composer
- Node.js & npm
- MySQL

### Installation

1. **Clone the repository:**
   ```bash
   git clone https://github.com/your-username/sae.git
   cd sae
   ```

2. **Install dependencies:**
   ```bash
   composer install
   npm install
   ```

3. **Environment setup:**
   - Copy the `.env.example` file to `.env`:
     ```bash
     cp .env.example .env
     ```
   - Generate an application key:
     ```bash
     php artisan key:generate
     ```
   - Configure your database credentials in the `.env` file.

4. **Database migration:**
   ```bash
   php artisan migrate --seed
   ```

5. **Run the development servers:**
   ```bash
   # In one terminal
   npm run dev

   # In another terminal
   php artisan serve
   ```

## License

Este proyecto es software de código abierto bajo la licencia [GNU Affero General Public License v3.0 (AGPL-3.0)](LICENSE).