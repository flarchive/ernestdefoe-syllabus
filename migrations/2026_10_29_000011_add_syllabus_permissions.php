<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

// Anyone may browse the catalogue, members may enrol, moderators teach and
// manage. Instructors are whichever group an admin gives "Create courses".
return Migration::addPermissions([
    'syllabus.view' => Group::GUEST_ID,
    'syllabus.enrol' => Group::MEMBER_ID,
    'syllabus.create' => Group::MODERATOR_ID,
    'syllabus.manage' => Group::MODERATOR_ID,
]);
