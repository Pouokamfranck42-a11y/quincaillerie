<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'contact_name', 'phone', 'email', 'address', 'lead_time_days', 'payment_terms'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function alternateProducts()
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
