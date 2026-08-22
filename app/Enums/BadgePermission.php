<?php
namespace App\Enums;
enum BadgePermission: string { case VIEW='badges.view'; case MANAGE='badges.manage'; case PRINT='badges.print'; case REISSUE='badges.reissue'; }
