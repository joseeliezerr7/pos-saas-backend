<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'color',
    ];

    /**
     * Relación con la empresa
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relación many-to-many con clientes
     */
    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'customer_customer_tag')
            ->withTimestamps();
    }

    /**
     * Scope para filtrar por empresa
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Obtener el total de clientes con este tag
     */
    public function getCustomerCountAttribute()
    {
        return $this->customers()->count();
    }
}
