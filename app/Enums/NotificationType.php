<?php

namespace App\Enums;

enum NotificationType: string
{
    case CustomerRegistered = 'customer.registered';
    case CustomerVerified = 'customer.verified';
    case CustomerRejected = 'customer.rejected';
    case OfficePending = 'office.pending';
    case OfficeApproved = 'office.approved';
    case OfficeRejected = 'office.rejected';
    case JobCreated = 'job.created';
    case JobAssigned = 'job.assigned';
    case JobUnassigned = 'job.unassigned';
    case JobStatus = 'job.status';

    public function label(): string
    {
        return match ($this) {
            self::CustomerRegistered => 'Neue Kundenregistrierung',
            self::CustomerVerified => 'Kunde bestätigt',
            self::CustomerRejected => 'Kunde abgelehnt',
            self::OfficePending => 'Büro wartet auf Freigabe',
            self::OfficeApproved => 'Büro freigegeben',
            self::OfficeRejected => 'Büro abgelehnt',
            self::JobCreated => 'Neuer Einsatz',
            self::JobAssigned => 'Einsatz zugewiesen',
            self::JobUnassigned => 'Zuweisung aufgehoben',
            self::JobStatus => 'Status aktualisiert',
        };
    }
}
