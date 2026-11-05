# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

TALL stack: Laravel 12, Livewire 4 & Volt, Tailwind CSS 4, MaryUI Component Library, Alpine.js, MySQL

## Users

Primary users are **teachers** and **students**. Teachers manage classes, attendance, grades, and subject content. Students access their coursework, grades, and materials. School administrators also use the system for financial control, reporting, and system-wide management.

## Product Purpose

SAE (Sistema de Administración Escolar) is an all-in-one school management platform that digitizes and streamlines every aspect of running a school — from academic management and financial control to communication and reporting — in a single reactive web application.

## Positioning

A comprehensive TALL-stack platform that integrates academic management, financial control, real-time communication, and reporting into one cohesive system, replacing fragmented paper-based or standalone tools.

## Operating Context

- Teachers use the system daily to manage class sessions, record attendance, track grades, and organize subject materials
- Students access coursework, view grades, and communicate with teachers
- Administrators oversee financial operations, generate reports, and manage school-wide data
- The system operates in a Spanish-speaking educational context (evidenced by manual documentation in Spanish)
- Development and deployment uses Laravel Herd, MySQL, and Vite-based frontend bundling

## Capabilities and Constraints

- **Academic Management**: Manage careers, subjects, modalities, enrollments, class sessions, attendance, and grades
- **Financial Control**: Payment plans, installments, payment tracking, and financial reporting via MercadoPago integration
- **Communication**: Real-time chat, calendar and event system, content management for subject materials
- **Reporting**: Student report cards, attendance records, statistical reports
- **Must preserve**: AGPL-3.0 license, existing database schema, MaryUI component library
- **Undecided**: Specific accessibility standards beyond baseline web accessibility

## Brand Commitments

- **Name**: SAE — Sistema de Administración Escolar
- **License**: AGPL-3.0-or-later (open source)
- **Language**: Spanish-primary documentation and interface; English as fallback

## Evidence on Hand

- `README.md` — Project overview and setup instructions
- `manual_docente.md` — Teacher user manual (Spanish)
- `manual_usuario.md` — General user manual (Spanish)
- `2026-09-21_sae.sql`, `sae2026-09-16.sql` — Database schema and seed data
- `sae-routes.visual-check.*` — Visual regression test artifacts
- `docs/Manual_Profesor.md` — Additional teacher documentation

## Product Principles

1. **Completeness over fragmentation** — One platform replaces multiple disjointed tools for school administration
2. **Reactive and real-time** — Livewire and Volt power instant, server-driven interactions without page reloads
3. **Preserve and respect the system** — The AGPL-3.0 license, existing data structures, and MaryUI components are non-negotiable foundations
4. **Spanish-first, globally accessible** — Designed for Spanish-speaking educational communities while remaining usable internationally
5. **Teacher-centered design** — The system exists to make the teacher's daily workflow as smooth as possible

## Accessibility & Inclusion

No product-specific accessibility standard has been established. Baseline web accessibility is assumed.
