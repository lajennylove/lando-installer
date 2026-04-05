<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RemoteSite extends Model
{
    protected $guarded = [];

    protected $casts = [
        'ssh_password' => 'encrypted',
        'db_password' => 'encrypted',
        'install_composer_dependencies' => 'boolean',
        'install_node_dependencies' => 'boolean',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
