<?php
namespace App\Enums;
enum BadgeStatus: string { case ACTIVE='active'; case EXPIRED='expired'; case SUSPENDED='suspended'; case LOST='lost'; case REPLACED='replaced'; case CANCELLED='cancelled'; }
