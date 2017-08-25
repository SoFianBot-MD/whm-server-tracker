<?php

namespace App;

use App\Filters\ServerFilters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    protected $guarded = [];
    protected $with = ['settings'];
    protected $withCount = ['accounts'];
    protected $casts = ['backup_enabled' => 'boolean'];
    protected $dates = ['details_last_updated', 'accounts_last_updated'];
    protected $appends = [
        'formatted_server_type',
        'formatted_backup_days',
        'missing_token',
        'can_refresh_data',
        'whm_url',
        'settings'
    ];
    protected $hidden = ['token'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($server) {
            $server->accounts->each->delete();
        });
    }

    public function settings()
    {
        return $this->hasMany(Setting::class);
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function getSetting($name)
    {
        if (array_key_exists($name, $this->settings)) {
            return $this->settings[$name];
        }

        return null;
    }

    public function setSetting($name, $value)
    {
        if ($this->settings()->where('name', $name)->exists()) {
            return $this->settings()->where('name', $name)->update([
                'value' => $value
            ]);
        }

        return $this->settings()->create([
            'server_id' => $this->id,
            'name' => $name,
            'value' => $value
        ]);
    }

    public function removeSetting($name)
    {
        return $this->settings()->where('name', $name)->delete();
    }

    public function removeAllSettings()
    {
        return $this->settings()->delete();
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

    public function fetchDiskUsageDetails($serverConnector)
    {
        $diskUsage = $serverConnector->getDiskUsage();

        $this->update([
            'disk_used' => $diskUsage['used'],
            'disk_available' => $diskUsage['available'],
            'disk_total' => $diskUsage['total'],
            'disk_percentage' => $diskUsage['percentage'],
            'disk_last_updated' => Carbon::now()
        ]);

        return false;
    }

    public function fetchBackupDetails($serverConnector)
    {
        $backups = $serverConnector->getBackups();

        $this->update([
            'backup_enabled' => $backups['backupenable'],
            'backup_days' => $backups['backupdays'],
            'backup_retention' => $backups['backup_daily_retention'],
            'backup_last_updated' => Carbon::now()
        ]);

        return false;
    }

    public function fetchAccounts($serverConnector)
    {
        $accounts = $serverConnector->getAccounts();

        $this->processAccounts($accounts);

        $this->update([
            'accounts_last_updated' => Carbon::now()
        ]);

        return false;
    }

    public function processAccounts($accounts)
    {
        $config = config('server-tracker');

        collect($accounts)
            ->map(function ($item) {
                return [
                    'domain'         => $item['domain'],
                    'user'           => $item['user'],
                    'ip'             => $item['ip'],
                    'backup'         => $item['backup'],
                    'suspended'      => $item['suspended'],
                    'suspend_reason' => $item['suspendreason'],
                    'suspend_time'   => ($item['suspendtime'] != 0 ? Carbon::createFromTimestamp($item['suspendtime']) : null),
                    'setup_date'     => Carbon::parse($item['startdate']),
                    'disk_used'      => $item['diskused'],
                    'disk_limit'     => $item['disklimit'],
                    'plan'           => $item['plan']
                ];
            })->reject(function ($item) use ($config) {
                return in_array($item['user'], $config['ignore_usernames']);
            })->each(function ($item) {
                $this->addOrUpdateAccount($item);
            });

        $this->removeStaleAccounts($accounts);
    }

    public function addOrUpdateAccount($account)
    {
        if ($foundAccount = $this->findAccount($account['user'])) {
            return $foundAccount->update($account);
        }

        return $this->addAccount($account);
    }

    public function removeStaleAccounts($accounts)
    {
        $this->fresh()->accounts->filter(function ($item) use ($accounts) {
            if (collect($accounts)->where('user', $item['user'])->first()) {
                return false;
            }

            return true;
        })->each(function ($item) {
            $this->removeAccount($item);
        });
    }

    public function scopeFilter($query, ServerFilters $filters)
    {
        return $filters->apply($query);
    }

    public function getSettingsAttribute()
    {
        return $this->settings()->pluck('value','name')->all();
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
        if (! $this->backup_days) {
            return 'None';
        }

        return str_replace([0,1,2,3,4,5,6], ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'], $this->backup_days);
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
}
