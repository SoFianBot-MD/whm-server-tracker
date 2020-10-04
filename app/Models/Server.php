<?php

namespace App\Models;

use App\Enums\ServerTypeEnum;
use App\Filters\ServerFilters;
use App\Jobs\FetchServerAccounts;
use App\Jobs\FetchServerDetails;
use App\Models\Concerns\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * App\Models\Server
 *
 * @property int $id
 * @property string $name
 * @property string $address
 * @property int $port
 * @property ServerTypeEnum $server_type
 * @property string|null $token
 * @property string|null $notes
 * @property array $settings
 * @property \Illuminate\Support\Carbon|null $details_last_updated
 * @property \Illuminate\Support\Carbon|null $accounts_last_updated
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Account[] $accounts
 * @property-read int|null $accounts_count
 * @property-read mixed $can_refresh_data
 * @property-read mixed $formatted_backup_days
 * @property-read mixed $formatted_disk_available
 * @property-read mixed $formatted_disk_total
 * @property-read mixed $formatted_disk_used
 * @property-read mixed $formatted_php_version
 * @property-read mixed $formatted_server_type
 * @property-read mixed $missing_token
 * @property-read mixed $whm_url
 * @method static \Illuminate\Database\Eloquent\Builder|Server filter(\App\Filters\ServerFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Server newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Server newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Server query()
 * @method static \Illuminate\Database\Eloquent\Builder|Server search($search)
 * @method static \Illuminate\Database\Eloquent\Builder|Server whereAccountsLastUpdated($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Server whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Server whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Server whereDetailsLastUpdated($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Server whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Server whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Server whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Server wherePort($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Server whereServerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Server whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Server whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Server whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Server extends Model
{
    use HasFactory, Unguarded;

    protected $withCount = ['accounts'];
    protected $casts = [
        'server_type' => ServerTypeEnum::class,
        'settings' => 'json',
        'details_last_updated' => 'datetime',
        'accounts_last_updated' => 'datetime',
    ];
    protected $appends = [
        'formatted_server_type',
        'formatted_backup_days',
        'formatted_disk_used',
        'formatted_disk_available',
        'formatted_disk_total',
        'formatted_php_version',
        'missing_token',
        'can_refresh_data',
        'whm_url'
    ];
    protected $hidden = ['token'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($server) {
            $server->accounts->each->delete();
        });
    }

    public static function refreshData()
    {
        $servers = static::where('server_type', '!=', 'reseller')
            ->orderBy('name')
            ->get();

        $servers->each(function ($server) {
            FetchServerDetails::dispatch($server);
            FetchServerAccounts::dispatch($server);
        });

        return true;
    }

    public function settings()
    {
        return new Settings($this, $this->settings);
    }

    public function fetchers()
    {
        return new Fetchers($this);
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function addAccount($account)
    {
        return $this->accounts()->create($account);
    }

    public function removeAccount($account)
    {
        return $account->delete();
    }

    public function findAccount($username)
    {
        return $this->fresh()->accounts()->where('user', $username)->first();
    }

    public function scopeFilter($query, ServerFilters $filters)
    {
        return $filters->apply($query);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($query) use ($search) {
            $query->where('name', 'LIKE', '%' . $search . '%')
                ->orWhere('notes', 'LIKE', '%' . $search . '%');
        });
    }

    public function getFormattedServerTypeAttribute()
    {
        if ($this->server_type == 'vps') {
            return 'VPS';
        } elseif ($this->server_type == 'dedicated') {
            return 'Dedicated';
        }

        return 'Reseller';
    }

    public function getFormattedBackupDaysAttribute()
    {
        if (! $this->settings()->backup_days) {
            return 'None';
        }

        return str_replace([0, 1, 2, 3, 4, 5, 6], ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], $this->settings()->backup_days);
    }

    public function getFormattedDiskUsedAttribute()
    {
        if (! $this->settings()->disk_used) {
            return 'Unknown';
        }

        return $this->formatFileSize($this->settings()->disk_used);
    }

    public function getFormattedDiskAvailableAttribute()
    {
        if (! $this->settings()->disk_available) {
            return 'Unknown';
        }

        return $this->formatFileSize($this->settings()->disk_available);
    }

    public function getFormattedDiskTotalAttribute()
    {
        if (! $this->settings()->disk_total) {
            return 'Unknown';
        }

        return $this->formatFileSize($this->settings()->disk_total);
    }

    public function getFormattedPhpVersionAttribute()
    {
        if (! $this->settings()->php_version) {
            return 'Unknown';
        }

        $versions = [
            'ea-php54' => 'PHP 5.4',
            'ea-php55' => 'PHP 5.5',
            'ea-php56' => 'PHP 5.6',
            'ea-php70' => 'PHP 7.0',
            'ea-php71' => 'PHP 7.1',
            'ea-php72' => 'PHP 7.2',
            'ea-php73' => 'PHP 7.3',
            'ea-php74' => 'PHP 7.4',
        ];

        return Arr::get($versions, $this->settings()->php_version, 'Unknown');
    }

    public function getMissingTokenAttribute()
    {
        if ($this->server_type != 'reseller' && $this->token === null) {
            return true;
        }

        return false;
    }

    public function getCanRefreshDataAttribute()
    {
        if ($this->server_type == 'reseller' || $this->missing_token) {
            return false;
        }

        return true;
    }

    public function getWhmUrlAttribute()
    {
        if ($this->port == 2087) {
            return "https://{$this->address}:{$this->port}";
        }

        return "http://{$this->address}:{$this->port}";
    }

    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            $bytes = $this->trimTrailingZeroes(number_format($bytes / 1073741824, 2)) . ' TB';
        } elseif ($bytes >= 1048576) {
            $bytes = $this->trimTrailingZeroes(number_format($bytes / 1048576, 2)) . ' GB';
        } elseif ($bytes >= 1024) {
            $bytes = $this->trimTrailingZeroes(number_format($bytes / 1024, 2)) . ' MB';
        } else {
            $bytes = $bytes . ' KB';
        }

        return $bytes;
    }

    private function trimTrailingZeroes($number)
    {
        if (strpos($number, '.') !== false) {
            $number = rtrim($number, '0');
        }

        return rtrim($number, '.');
    }
}
