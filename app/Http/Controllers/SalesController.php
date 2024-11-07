<?php

namespace App\Http\Controllers;

use TCPDF;
use Carbon\Carbon;
use App\Models\Item;
use App\Models\Unit;
use App\Models\Sales;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class SalesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Sales::get();
        return view('content.Sales.index', compact('data'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $unit = Unit::get()->pluck('item_unit_name', 'id');
        $category = Category::get()
            ->pluck('item_category_name', 'id');
        $item = Item::get()->pluck('item_name', 'id');
        return view(
            'content.Sales.add',
            compact('unit', 'item', 'category')
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $sales = Sales::create($request->all());
            foreach ($request->sales_item as $val) {
                $sales->items()->create($val);
                $item = Item::find($val['item_id']);
                $stock = $item->stok()->first();
                $stock->update(['last_balance' => $stock->last_balance - $val['quantity']]);
            }
            DB::commit();
            return redirect()->route('sales.index')
                ->with([
                    'msg' => 'Input Sales Sukses',
                    'type' => 'success'
                ]);
        } catch (\Throwable $th) {
            DB::rollback();
            dd($th);
            report($th);
            return redirect()->route('sales.index')
                ->with([
                    'msg' => 'Input Sales Gagal',
                    'type' => 'danger'
                ]);

        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Sales $sale)
    {
        //
        // dd($sale);

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sales $sales)
    {
        //
    }



    public function print(Sales $sales)
    {
        $salesInvoice = Sales::with('items')
        ->where('data_state',0)
        ->first();
        // dd($salesInvoice);
        // Buat instance TCPDF
        $pdf = new TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Nama Penulis');
        $pdf->SetTitle('Kwitansi Penjualan');
        $pdf->SetSubject('Kwitansi Penjualan');
        $pdf->SetMargins(10, 10, 10);

        // Tambahkan halaman baru
        $pdf->AddPage();

        // Judul Kwitansi
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Kwitansi Penjualan', 0, 1, 'C');

        // Data Kwitansi
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Ln(10); // Tambah spasi

        // Tampilkan data penjualan dari objek $sales
        $html = '
            <table cellpadding="4" cellspacing="0" border="1">
                <tr>
                    <td><strong>No. Kwitansi</strong></td>
                    <td>' . $salesInvoice->id . '</td>
                </tr>
                <tr>
                    <td><strong>Tanggal</strong></td>
                    <td>' . Carbon::parse($salesInvoice->sales_invoice_date)->format('d-m-Y') . '</td>
                </tr>
                
                <tr>
                    <td><strong>Jumlah Pembayaran</strong></td>
                    <td>Rp ' . number_format($salesInvoice->subtotal_amount, 2, ',', '.') . '</td>
                </tr>
            </table>
        ';

        // Tambahkan HTML ke PDF
        $pdf->writeHTML($html, true, false, true, false, '');


        // Output PDF
        $pdf->Output('Kwitansi_Penjualan_' . $sales->invoice_number . '.pdf', 'I');
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Sales $sales)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sales $sale)
    {
        try {
            DB::beginTransaction();
            // dd($sale);
            $sale->delete();
            $sale->items()->delete();
            DB::commit();
            return redirect()->route('sales.index')
                ->with(['msg' => 'Hapus Sales Sukses', 'type' => 'success']);

        } catch (\Throwable $th) {
            DB::rollback();
            report($th);
            return redirect()->route('sales.index')
                ->with(['msg' => 'Hapus Sales Gagal', 'type' => 'danger']);

        }
    }

    public function addItem(Request $request)
    {
        $item = Item::findOrFail($request->item_id);
        return response()->json($item);
    }
}
