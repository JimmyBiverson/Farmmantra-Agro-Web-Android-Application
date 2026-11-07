<?php

namespace App\Http\Controllers\Api\Operations;

use App\Http\Controllers\Controller;
use App\Models\FranchiseInventory;
use App\Models\OperationRequest;
use App\Models\Order;
use App\Models\Sale;
use App\Models\WarehouseInventory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function download(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isFinance() || $request->user()->isSalesHead(), 403);
        $data = $request->validate(['report' => ['required', Rule::in(['warehouse', 'franchise_stock', 'outstanding', 'sales', 'expenses'])], 'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $type = $data['report'];
        $rows = match ($type) {
            'warehouse' => WarehouseInventory::with('product')->get()->map(fn ($r) => ['Central warehouse', $r->product?->sku, $r->product?->name, $r->quantity, $r->product?->batch_number, $r->product?->manufactured_at, $r->product?->expires_at]),
            'franchise_stock' => FranchiseInventory::with(['product', 'franchise'])->get()->map(fn ($r) => [$r->franchise?->name, $r->product?->sku, $r->product?->name, $r->quantity, $r->total_value, $r->product?->batch_number, $r->product?->expires_at]),
            'outstanding' => Order::with('franchise')->whereNotNull('received_at')->whereRaw('COALESCE(received_amount, total_amount) > amount_paid')->get()->map(fn ($r) => [$r->franchise?->name, $r->order_number, $r->received_at->toDateString(), $r->received_at->copy()->addDays(15)->toDateString(), (int) $r->received_at->diffInDays(now()), $r->balance_due]),
            'sales' => Sale::with(['franchise', 'customer'])->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('sale_date', '>=', $v))->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('sale_date', '<=', $v))->get()->map(fn ($r) => [$r->sale_number, $r->franchise?->name, $r->customer?->name, $r->sale_date->toDateString(), $r->final_amount, $r->total_cost, round((float) $r->final_amount - (float) $r->total_cost, 2)]),
            default => OperationRequest::with('user')->where('type', 'expense')->when($data['from'] ?? null, fn ($q, $v) => $q->whereDate('entry_date', '>=', $v))->when($data['to'] ?? null, fn ($q, $v) => $q->whereDate('entry_date', '<=', $v))->get()->map(fn ($r) => [$r->number, $r->user?->name, $r->title, $r->entry_date->toDateString(), $r->amount, $r->status]),
        };
        $headers = match ($type) {
            'warehouse' => ['Location', 'Code', 'Product', 'Quantity', 'Batch', 'Manufactured', 'Expiry'],
            'franchise_stock' => ['Franchise', 'Code', 'Product', 'Quantity', 'Value', 'Batch', 'Expiry'],
            'outstanding' => ['Franchise', 'Order', 'Received', 'Due', 'Age (days)', 'Outstanding'],
            'sales' => ['Sale', 'Franchise', 'Customer', 'Date', 'Sales', 'Cost', 'Margin'],
            default => ['Reference', 'Submitted by', 'Expense', 'Date', 'Amount', 'Status'],
        };
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach (collect([$headers])->concat($rows) as $index => $row) {
            $xml .= '<row r="'.($index + 1).'">';
            foreach ($row as $value) {
                $xml .= '<c t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        $path = tempnam(sys_get_temp_dir(), 'farmmantra-export-');
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to create export.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        return response()->download($path, 'farmmantra-'.$type.'-'.now()->format('Ymd').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store'])->deleteFileAfterSend();
    }
}
