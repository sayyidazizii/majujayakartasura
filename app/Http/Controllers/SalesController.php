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



    public function print($sales)
    {
        $salesInvoice = Sales::with(['items.item'])
            ->where('data_state', 0)
            ->where('id', $sales)
            ->first();

            // dd($sales);

        // Buat instance TCPDF dengan ukuran kertas custom untuk struk
        $pdf = new TCPDF('P', 'mm', array(80, 200)); // 80 mm lebar, 200 mm panjang
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Nama Penulis');
        $pdf->SetTitle('Kwitansi Penjualan');
        $pdf->SetSubject('Kwitansi Penjualan');
        $pdf->SetMargins(5, 5, 5); // Margins kecil untuk struk

        // Tambahkan halaman baru
        $pdf->AddPage();

        // Judul Kwitansi
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Kwitansi Penjualan', 0, 1, 'C');

        // Data Kwitansi
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Ln(2);

        $html = '
            <table cellpadding="3" cellspacing="0" border="0">
                <tr>
                    <td><strong>No. Kwitansi:</strong></td>
                    <td>' . $salesInvoice->id . '</td>
                </tr>
                <tr>
                    <td><strong>Tanggal:</strong></td>
                    <td>' . Carbon::parse($salesInvoice->sales_invoice_date)->format('d-m-Y') . '</td>
                </tr>
                <tr>
                    <td><strong>Pelanggan:</strong></td>
                    <td>' . $salesInvoice->customer_name . '</td>
                </tr>
            </table>
        ';

        $pdf->writeHTML($html, true, false, true, false, '');

        // Loop untuk item penjualan
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 10, 'Detail Item:', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 9);
        $htmlItems = '<table cellpadding="2" cellspacing="0" border="1" width="100%">
            <tr>
                <th>Nama Item</th>
                <th>Qty</th>
                <th>Harga</th>
                <th>Total</th>
            </tr>';

        foreach ($salesInvoice->items as $item) {
            $htmlItems .= '
                <tr>
                    <td>' . $item->item->item_name . '</td>
                    <td>' . $item->quantity . '</td>
                    <td>Rp ' . number_format($item->item_unit_price, 2, ',', '.') . '</td>
                    <td>Rp ' . number_format($item->item_unit_price *  $item->quantity , 2, ',', '.') . '</td>
                </tr>
            ';
        }

        $htmlItems .= '</table>';
        $pdf->writeHTML($htmlItems, true, false, true, false, '');

        // Total Pembayaran
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 10, 'Total: Rp ' . number_format($salesInvoice->subtotal_amount, 2, ',', '.'), 0, 1, 'R');

        // Tanda tangan
        $pdf->Ln(10);
        $pdf->Cell(0, 10, 'Tanda Tangan', 0, 1, 'R');
        $pdf->Ln(15);
        $pdf->Cell(0, 10, '(Nama Penerima)', 0, 1, 'R');

        // Output PDF
        $pdf->Output('Kwitansi_Penjualan_' . $sales . '.pdf', 'I');
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
