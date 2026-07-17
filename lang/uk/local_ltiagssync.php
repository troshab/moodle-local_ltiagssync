<?php
// Copyright (c) 2026 Trokhym Babych. All rights reserved.
//
// This software is proprietary and confidential. Unauthorized copying,
// distribution, modification, or use of this file, via any medium, is
// strictly prohibited without the prior written permission of the
// copyright holder.

/**
 * Ukrainian strings for local_ltiagssync.
 *
 * @package    local_ltiagssync
 * @copyright  2026 Trokhym Babych
 * @license    Proprietary. All rights reserved.
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'LTI Advantage: поелементна синхронізація оцінок';
$string['tasksyncgrades'] = 'Синхронізація поелементних оцінок на LTI-платформи (AGS)';
$string['enabled'] = 'Увімкнути поелементну синхронізацію оцінок';
$string['enabled_desc'] = 'Коли увімкнено, курси, опубліковані через "Publish as LTI tool", віддають кожну оцінювану активність окремим AGS line item (колонкою журналу на платформі-консюмері) на додачу до підсумку курсу, який enrol_lti надсилає типово.';
$string['includemanual'] = 'Включати ручні елементи оцінювання';
$string['includemanual_desc'] = 'Також синхронізувати створені вручну елементи журналу (тип "manual"), а не лише оцінки активностей (модулів).';
$string['privacy:metadata:local_ltiagssync_state'] = 'Значення поелементних оцінок, уже синхронізованих на платформу-консюмер, зберігаються, щоб не надсилати незмінені оцінки повторно.';
$string['privacy:metadata:local_ltiagssync_state:ltiuserid'] = 'Запис зарахування користувача платформи, чию оцінку синхронізовано.';
$string['privacy:metadata:local_ltiagssync_state:gradeitemid'] = 'Елемент журналу активності, до якого належить оцінка.';
$string['privacy:metadata:local_ltiagssync_state:lastgrade'] = 'Останнє значення оцінки, надіслане на платформу.';
$string['privacy:metadata:local_ltiagssync_state:timemodified'] = 'Коли оцінку востаннє надіслано.';
