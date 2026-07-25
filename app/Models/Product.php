<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'brand_id', 'sku', 'barcode', 'name', 'description', 'product_type',
        'tracking_type', 'purchase_unit_id', 'stock_unit_id', 'sale_unit_id', 'default_tax_id',
        'costing_method', 'standard_cost', 'default_sale_price', 'minimum_stock', 'maximum_stock',
        'reorder_quantity', 'warranty_months', 'is_sellable', 'is_purchasable', 'is_consumable', 'is_active',
    ];

    protected $casts = [
        'standard_cost' => 'decimal:4', 'default_sale_price' => 'decimal:4',
        'minimum_stock' => 'decimal:6', 'maximum_stock' => 'decimal:6', 'reorder_quantity' => 'decimal:6',
        'is_sellable' => 'boolean', 'is_purchasable' => 'boolean', 'is_consumable' => 'boolean', 'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'brand_id');
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function stockUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'stock_unit_id');
    }

    public function saleUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'sale_unit_id');
    }

    public function defaultTax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'default_tax_id');
    }

    public function filmProfile(): HasOne
    {
        return $this->hasOne(FilmProductProfile::class);
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(ProductUnitConversion::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function rolls(): HasMany
    {
        return $this->hasMany(InventoryRoll::class);
    }

    public function transferItems(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function serviceMaterialRequirements(): HasMany
    {
        return $this->hasMany(ServiceMaterialRequirement::class);
    }

    public function serviceRollProfiles(): HasMany
    {
        return $this->hasMany(ServiceRollConsumptionProfile::class, 'film_product_id');
    }
}
