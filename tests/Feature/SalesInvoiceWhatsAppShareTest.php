<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\InvoiceShare;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SalesInvoiceWhatsAppShareTest extends TestCase
{
    use DatabaseTransactions;

    public function test_button_opens_whatsapp_for_invoice_snapshot_phone_with_invoice_link(): void
    {
        [$user, $invoice] = $this->context('+20 101 112 2233');

        $response = $this->actingAs($user)
            ->post(route('sales-invoices.share', $invoice), ['phone' => '201999999999']);

        $response->assertOk()->assertSee('جاري فتح محادثة واتساب');
        preg_match('/https:\/\/web\.whatsapp\.com\/send\?phone=201011122233&amp;text=([^"<]+)/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches);
        parse_str('text='.html_entity_decode($matches[1]), $query);
        $this->assertStringContainsString($invoice->invoice_number, $query['text']);
        $this->assertStringContainsString('فاتورة وكارت ضمان Seven Ways', $query['text']);
        $this->assertStringContainsString('عرض الفاتورة وكارت الضمان:', $query['text']);
        $this->assertStringContainsString('/shared/invoices/', $query['text']);
        $this->assertDatabaseHas('invoice_shares', [
            'sales_invoice_id' => $invoice->id,
            'destination' => '201011122233',
            'channel' => 'whatsapp',
        ]);

        $this->get(route('sales-invoices.show', $invoice))
            ->assertOk()
            ->assertSee('إرسال الفاتورة وكارت الضمان عبر واتساب')
            ->assertDontSee('target="_blank"', false);
    }

    public function test_invalid_invoice_phone_is_rejected_without_creating_share(): void
    {
        [$user, $invoice] = $this->context('غير مسجل');

        $this->actingAs($user)
            ->from(route('sales-invoices.show', $invoice))
            ->post(route('sales-invoices.share', $invoice))
            ->assertRedirect(route('sales-invoices.show', $invoice))
            ->assertSessionHasErrors('phone');

        $this->assertFalse(InvoiceShare::where('sales_invoice_id', $invoice->id)->exists());
    }

    private function context(string $phone): array
    {
        $currency = Currency::firstOrCreate(
            ['code' => 'EGP'],
            ['name_ar' => 'جنيه مصري', 'name_en' => 'Egyptian Pound', 'symbol' => 'ج.م', 'decimal_places' => 2, 'is_active' => true]
        );
        $company = Company::create(['name' => 'WhatsApp '.uniqid(), 'currency_id' => $currency->id, 'is_active' => true]);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'WA'.uniqid(), 'name' => 'Branch', 'is_main' => true, 'is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status' => 'active']);
        $role = Role::create(['company_id' => $company->id, 'name' => 'invoice_share', 'display_name' => 'Invoice share', 'scope' => 'branch', 'is_active' => true]);
        foreach (['sales_invoices.view', 'sales_invoices.share'] as $name) {
            $role->permissions()->syncWithoutDetaching(Permission::firstOrCreate(['name' => $name], ['display_name' => $name]));
        }
        $user->roles()->attach($role);
        $user->accessibleBranches()->attach($branch->id, ['is_default' => true, 'can_view' => true]);
        $customer = Customer::factory()->create(['company_id' => $company->id, 'created_branch_id' => $branch->id, 'assigned_branch_id' => $branch->id]);
        $invoice = SalesInvoice::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'currency_id' => $currency->id,
            'created_by' => $user->id,
            'status' => 'issued',
            'customer_phone_snapshot' => $phone,
        ]);

        return [$user, $invoice];
    }
}
