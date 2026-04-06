# certificates_validation_wp_plugin
Certificate Validation Plugin is a custom WordPress plugin for organizations, training centers, schools, academies, and certification providers that need a simple way to publish and verify issued certificates online.

The plugin includes two main parts:

Public certificate verification
Users can validate a certificate without logging in by entering its certificate number on a page that contains the shortcode:
[certificate_validation]
The plugin performs an exact code match and displays the certificate details in a clean result card.

Admin certificate management
Site administrators can manage certificates from the WordPress dashboard, including:
creating certificates manually
editing existing certificates
deleting single or multiple certificates
searching certificates by code
importing certificates in bulk from .xlsx
What The Plugin Stores
Each certificate can contain:

certificate code
full name
course
hours
ECTS hours
issued date
optional course/info link
Main Features

public certificate validation by exact code
admin certificate CRUD
bulk .xlsx import
duplicate code protection
import report with skipped-row reasons
frontend language switch for English / Ukrainian labels
Ukrainian frontend date formatting when Ukrainian is selected
custom database table for certificate storage
shortcode-based frontend integration
nonce checks, sanitization, escaping, and prepared database queries
How It Works

On activation, the plugin creates its own certificates table in the WordPress database.
Admin users manage certificate records in the Certificates menu.
The frontend shortcode renders a search form.
When a visitor enters a certificate number, the plugin checks the database and returns the matching certificate if it exists.
If the frontend display language is set to Ukrainian in Certificates > Tools, the public result labels and issued date are displayed in Ukrainian.
Where It Can Be Used
This plugin is suitable for:

training centers
online schools
universities or educational programs
HR / internal training departments
seminar and workshop providers
any business that issues certificate numbers and wants public online verification
Admin Sections
The plugin adds these admin pages:

Certificates — list of all certificates
Add Certificate — create or edit a certificate
Bulk Upload — import certificates from .xlsx
Tools — shortcode reference and frontend language setting
Bulk Import Format
The .xlsx import expects this exact header structure:

code | full_name | course | hours | ects_hours | issued_date | link
Only valid rows are imported. Invalid rows are skipped and reported.

Requirements / Notes

WordPress 6.5+
PHP 7.4+
.xlsx bulk import requires PHP ZipArchive
frontend display language setting affects public certificate display only
admin interface remains in English
