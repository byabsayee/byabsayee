<?php
namespace App\Services\Integration;

use App\Services\Integration\Mappers as M;

final class Registry
{
    private const MAP = [
        'category' => M\CategoryMapper::class, 'product' => M\ProductMapper::class, 'customer' => M\CustomerMapper::class,
        'order' => M\OrderMapper::class, 'payment' => M\PaymentMapper::class, 'payment_method' => M\PaymentMethodMapper::class,
        'stock_movement' => M\StockMapper::class, 'coupon' => M\CouponMapper::class, 'tax' => M\TaxMapper::class,
        'delivery_charge' => M\DeliveryMapper::class, 'return' => M\ReturnMapper::class,
    ];

    /** @return class-string<M\BaseMapper> */
    public static function mapper(string $entity): string
    {
        if (!isset(self::MAP[$entity])) throw new \InvalidArgumentException('Unknown entity ' . $entity);
        return self::MAP[$entity];
    }
}
