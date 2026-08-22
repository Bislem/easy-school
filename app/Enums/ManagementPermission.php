<?php
namespace App\Enums;
enum ManagementPermission:string {case CERTIFICATES_VIEW='certificates.view';case CERTIFICATES_ISSUE='certificates.issue';case CERTIFICATES_PRINT='certificates.print';case REPORTS_VIEW='reports.view';case REPORTS_EXPORT='reports.export';case AUDIT_VIEW='audit.view';}
