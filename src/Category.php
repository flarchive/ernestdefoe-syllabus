<?php

namespace Ernestdefoe\Syllabus;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $position
 * @property Carbon|null $created_at
 */
class Category extends AbstractModel
{
    protected $table = 'syllabus_categories';

    public $timestamps = false;

    protected $casts = [
        'position' => 'integer',
        'created_at' => 'datetime',
    ];
}
