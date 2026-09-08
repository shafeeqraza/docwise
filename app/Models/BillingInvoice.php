<?php

namespace App\Models;

use App\Enums\BillingInvoiceStatus;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'invoice_number',
        'period_start',
        'period_end',
        'base_amount',
        'overage_amount',
        'total_amount',
        'status',
        'stripe_invoice_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'base_amount' => 'decimal:2',
            'overage_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'status' => BillingInvoiceStatus::class,
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->invoice_number)) {
                $model->invoice_number = static::generateInvoiceNumber();
            }
        });
    }

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // Helper methods
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $year = now()->year;
        $month = now()->format('m');
        
        $lastInvoice = static::whereYear('created_at', $year)
            ->whereMonth('created_at', now()->month)
            ->orderBy('id', 'desc')
            ->first();
        
        $sequence = $lastInvoice ? (int) substr($lastInvoice->invoice_number, -4) + 1 : 1;
        
        return sprintf('%s-%d%s-%04d', $prefix, $year, $month, $sequence);
    }

    public function isDraft(): bool
    {
        return $this->status === BillingInvoiceStatus::DRAFT;
    }

    public function isSent(): bool
    {
        return $this->status === BillingInvoiceStatus::SENT;
    }

    public function isPaid(): bool
    {
        return $this->status === BillingInvoiceStatus::PAID;
    }

    public function isOverdue(): bool
    {
        return $this->status === BillingInvoiceStatus::OVERDUE;
    }

    public function hasOverage(): bool
    {
        return $this->overage_amount > 0;
    }

    public function getOveragePercentage(): float
    {
        if ($this->base_amount == 0) {
            return 0.0;
        }
        
        return ($this->overage_amount / $this->base_amount) * 100;
    }

    public function getDaysOverdue(): ?int
    {
        if (!$this->isOverdue()) {
            return null;
        }
        
        // Assuming invoices are due 30 days after creation
        $dueDate = $this->created_at->addDays(30);
        return $dueDate->diffInDays(now());
    }

    public function getPeriodLabel(): string
    {
        return $this->period_start->format('M j') . ' - ' . $this->period_end->format('M j, Y');
    }

    public function getFormattedAmount(string $field = 'total_amount'): string
    {
        $amount = $this->{$field};
        return '$' . number_format($amount, 2);
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => BillingInvoiceStatus::PAID,
            'paid_at' => now(),
        ]);
    }

    public function markAsOverdue(): void
    {
        $this->update(['status' => BillingInvoiceStatus::OVERDUE]);
    }

    public function getPaymentUrl(): ?string
    {
        if (!$this->stripe_invoice_id) {
            return null;
        }
        
        // Return Stripe payment URL
        return "https://invoice.stripe.com/i/{$this->stripe_invoice_id}";
    }
}