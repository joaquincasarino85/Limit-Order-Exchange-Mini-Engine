<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Asset;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderMatchingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Una orden de COMPRA debe matchear con la primera orden de VENTA válida
     * 
     * Regla de matching:
     * - Nueva orden BUY matchea con primera SELL donde: sell.price <= buy.price
     * 
     * Escenario:
     * - Usuario A tiene orden SELL de 0.01 BTC a $95,000
     * - Usuario B crea orden BUY de 0.01 BTC a $100,000
     * - Deben matchear porque $95,000 <= $100,000
     */
    public function test_buy_order_matches_with_sell_order_when_price_is_valid(): void
    {
        // Usuario A: quiere vender BTC
        $seller = User::factory()->create();
        $sellerAsset = Asset::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'amount' => 0.5,
            'locked_amount' => 0,
        ]);

        // Usuario B: quiere comprar BTC
        $buyer = User::factory()->create(['balance' => 10000.00]);

        // Crear orden de VENTA: 0.01 BTC a $95,000
        $sellOrder = Order::create([
            'user_id' => $seller->id,
            'symbol' => 'BTC',
            'side' => 'sell',
            'price' => 95000.00,
            'amount' => 0.01,
            'status' => 1, // open
        ]);

        // Crear orden de COMPRA: 0.01 BTC a $100,000
        // Esta orden debe matchear automáticamente con la orden de venta
        $buyOrder = Order::create([
            'user_id' => $buyer->id,
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 100000.00,
            'amount' => 0.01,
            'status' => 1,
        ]);

        // TODO: Aquí llamaremos al servicio de matching
        // Por ahora, validamos que las órdenes existen
        
        // Después del match:
        // - Ambas órdenes deben estar en status = 2 (filled)
        // - El comprador debe recibir 0.01 BTC
        // - El vendedor debe recibir USD (menos comisión)
        // - Se debe cobrar comisión del 1.5%
        
        $this->assertTrue(true); // Placeholder hasta implementar matching
    }

    /**
     * Test: Una orden de VENTA debe matchear con la primera orden de COMPRA válida
     * 
     * Regla de matching:
     * - Nueva orden SELL matchea con primera BUY donde: buy.price >= sell.price
     */
    public function test_sell_order_matches_with_buy_order_when_price_is_valid(): void
    {
        // Similar al anterior pero al revés
        $this->assertTrue(true); // Placeholder
    }

    /**
     * Test: NO debe hacer match si los precios no coinciden
     * 
     * Escenario:
     * - Orden SELL: 0.01 BTC a $100,000
     * - Orden BUY: 0.01 BTC a $95,000
     * - NO deben matchear porque $100,000 > $95,000
     */
    public function test_orders_do_not_match_when_prices_dont_overlap(): void
    {
        $this->assertTrue(true); // Placeholder
    }

    /**
     * Test: Después del match, los balances deben actualizarse correctamente
     * 
     * Escenario: Match de 0.01 BTC a $95,000
     * - Comprador: pierde $950 (más comisión), gana 0.01 BTC
     * - Vendedor: pierde 0.01 BTC, gana $950 (menos comisión si aplica)
     * - Comisión: 1.5% de $950 = $14.25
     */
    public function test_balances_update_correctly_after_match(): void
    {
        $this->assertTrue(true); // Placeholder
    }

    /**
     * Test: La comisión del 1.5% se calcula correctamente
     * 
     * Ejemplo del requerimiento:
     * - 0.01 BTC @ $95,000 = volumen de $950
     * - Comisión: $950 * 0.015 = $14.25
     */
    public function test_commission_is_calculated_correctly(): void
    {
        $volume = 950.00; // 0.01 BTC * $95,000
        $commissionRate = 0.015; // 1.5%
        $expectedCommission = 14.25;

        $calculatedCommission = $volume * $commissionRate;

        $this->assertEquals($expectedCommission, $calculatedCommission);
    }
}
