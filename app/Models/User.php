<?php

namespace App\Models;

use App\Domain\Identity\User as DomainUser;

/**
 * Canonical user model lives in Domain\Identity.
 * This alias keeps Laravel defaults (factories, auth) working.
 */
class User extends DomainUser {}
