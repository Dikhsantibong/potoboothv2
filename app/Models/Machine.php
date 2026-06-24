<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Machine extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'payment_required',
        'token',
        'amount_koran',
        'amount_reguler',
        'amount_flipbook',
        'amount_print_koran',
        'amount_print_reguler',
        'amount_print_flipbook',
        'paper_capacity',
        'paper_reset_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'payment_required' => 'boolean',
        'amount_koran' => 'integer',
        'amount_reguler' => 'integer',
        'amount_flipbook' => 'integer',
        'amount_print_koran' => 'integer',
        'amount_print_reguler' => 'integer',
        'amount_print_flipbook' => 'integer',
        'paper_capacity' => 'integer',
        'paper_reset_at' => 'datetime',
    ];

    protected $appends = ['paper_condition'];

    public function getPaperConditionAttribute()
    {
        $used = \App\Models\Transaction::where('transactions.machine_id', $this->id)
            ->where('transactions.status', 'COMPLETED')
            ->when($this->paper_reset_at, function ($q) {
                return $q->where('transactions.finished_at', '>=', $this->paper_reset_at);
            })
            ->whereHas('template', function($q) {
                $q->whereIn('templates.type', ['flipbook', 'reguler']);
            })
            ->join('final_images', 'transactions.id', '=', 'final_images.transaction_id')
            ->join('templates', 'transactions.template_id', '=', 'templates.id')
            ->selectRaw('SUM(CASE WHEN templates.type = "flipbook" THEN final_images.print_quantity * 10 ELSE final_images.print_quantity * 1 END) as total_used')
            ->value('total_used');

        $used = (int) $used;
        $initialStock = $this->paper_capacity ?? 700;
        $remaining = max(0, $initialStock - $used);
        $percentage = $initialStock > 0 ? ($remaining / $initialStock) * 100 : 0;

        $indicator = 'merah';
        if ($percentage > 50) {
            $indicator = 'hijau';
        } elseif ($percentage >= 20) {
            $indicator = 'kuning';
        }

        return [
            'initial_stock' => $initialStock,
            'total_used' => $used,
            'remaining' => $remaining,
            'percentage' => round($percentage, 2),
            'indicator' => $indicator,
            'last_reset' => $this->paper_reset_at ? $this->paper_reset_at->format('Y-m-d H:i:s') : null,
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($machine) {
            $machine->token = Str::random(8);

            // Ensure uniqueness
            while (self::where('token', $machine->token)->exists()) {
                $machine->token = Str::random(8);
            }
        });
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function templates()
    {
        return $this->hasMany(Template::class);
    }

    public function stickers()
    {
        return $this->hasMany(Sticker::class);
    }

    public function paperSizes()
    {
        return $this->hasMany(PaperSize::class);
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class);
    }
}
