-- Migration: add type_note to notes table and unique constraint
-- Run this on your database before using the new dual-grade entry UI.

ALTER TABLE `notes`
  ADD COLUMN `type_note` ENUM('classe','examen') NOT NULL DEFAULT 'classe' AFTER `note`;

-- Backfill existing rows implicitly set to 'classe' by default value
-- Ensure uniqueness per (etudiant, matiere, annee, type)
ALTER TABLE `notes`
  ADD UNIQUE KEY `uniq_note_type` (`etudiant_id`,`matiere_id`,`annee_academique`,`type_note`);
