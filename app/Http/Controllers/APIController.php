<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Models\User;
use App\Models\CoreRecipe;
use App\Models\PreferenceCompany;
use App\Models\Category as InvtItemCategory;
use App\Models\Unit as InvtItemUnit;
use App\Models\Item as InvtItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\CapitalMoney;
use App\Models\Expenditure;
use App\Models\Stock as InvtItemStock;
use App\Models\JournalVoucher;
use App\Models\JournalVoucherItem;
use App\Models\PreferenceTransactionModule;
use App\Models\AcctAccountSetting;
use App\Models\AcctAccount;
use App\Models\SystemLoginLog;
use App\Models\InvtWarehouse;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceSarmed;
use App\Models\PurchaseInvoiceItemSarmed;
use App\Models\SalesInvoiceSarmed;
use App\Models\SalesInvoiceItemSarmed;
use App\Models\SalesConsignmentSarmed;
use App\Models\SalesConsignmentItemSarmed;

class APIController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth');
    }

    public function login(Request $request)
    {
        $fields = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Check username
        $user = User::select('system_user.*', 'system_user_group.user_group_name', 'preference_company.guest_state')
            ->join('system_user_group', 'system_user_group.user_group_id', 'system_user.user_group_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('name', $fields['username'])
            ->first();

        //Check password
        if (!Hash::check($fields['password'], $user->password)) {
            return response([
                'message' => 'Username / Password Tidak Sesuai'
            ], 401);
        }

        $login_log = array(
            'user_id' => $user['user_id'],
            'company_id' => $user['company_id'],
            'log_time' => date("Y-m-d H:i:s"),
            'log_status' => 0,
            'created_at' => date("Y-m-d H:i:s")
        );

        // SystemLoginLog::create($login_log);

        $token = $user->createToken('token-name')->plainTextToken;
        $response = [
            'data' => $user,
            'token' => $token,
        ];

        return response($response, 201);
    }

    public function logout(Request $request)
    {
        $user = auth()->user();
        $user_state = User::findOrFail($user['user_id']);
        $user_state->save();

        auth()->user()->tokens()->delete();

        $login_log = array(
            'user_id' => $user['user_id'],
            'company_id' => $user['company_id'],
            'log_time' => date("Y-m-d H:i:s"),
            'log_status' => 1,
            'created_at' => date("Y-m-d H:i:s")
        );

        // SystemLoginLog::create($login_log);

        return [
            'message' => 'Logged Out'
        ];
    }

    public function userProfile(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required|string',
        ]);

        // Check username
        $user = User::select('system_user.name, system_user.created_at, system_user_group.user_group_name')
            ->join('system_user_group', 'system_user_group.user_group_id', 'system_user.user_gorup_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $response = [
            'data' => $user,
        ];

        return response($response, 201);
    }

    public function changePassword(Request $request)
    {
        $fields = $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string',
            'user_id' => 'required|string',
        ]);

        // Check username
        $user = User::findOrFail($fields['user_id']);

        if (!Hash::check($fields['old_password'], $user->password)) {
            return response([
                'message' => 'Password Lama Tidak Sesuai'
            ], 401);
        }

        $user->password = Hash::make($fields['new_password']);
        if ($user->save()) {
            return response([
                'message' => 'Ganti Password Berhasil'
            ], 201);
        } else {
            return response([
                'message' => 'Ganti Password Tidak Berhasil'
            ], 401);
        }
    }

    public function printerAddress(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required|string',
        ]);

        // Check username
        $preferencecompany = User::select('preference_company.printer_address')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        if ($preferencecompany) {
            return response([
                'data' => $preferencecompany['printer_address'],
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Ditemukan'
            ], 401);
        }
    }

    public function updatePrinterAddress(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required|string',
            'printer_address' => 'required|string',
        ]);

        // Check username
        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        // $preferencecompany = PreferenceCompany::findOrFail($company_id['company_id']);
        // $preferencecompany->printer_address = $fields['printer_address'];

        return response([
            'message' => 'Ganti Alamat Printer Berhasil'
        ], 201);
        if ($preferencecompany->save()) {
            return response([
                'message' => 'Ganti Alamat Printer Berhasil'
            ], 201);
        } else {
            return response([
                'message' => 'Ganti Alamat Printer Tidak Berhasil'
            ], 401);
        }
    }

    function sortByItemCategoryId($a, $b)
    {
        if ($a['item_category_id'] > $b['item_category_id']) {
            return 1;
        } elseif ($a['item_category_id'] < $b['item_category_id']) {
            return -1;
        }
        return 0;
    }

    public function getInvtItemCategory(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required|string',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $invtcategory = InvtItemCategory::select('*')
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->orderBy('item_category_name', 'ASC')
            ->get();

        $total = 0;
        foreach ($invtcategory as $key => $val) {
            $stock_total = InvtItemStock::where('item_category_id', $val['item_category_id'])
                ->where('data_state', 0)
                ->sum('last_balance');

            if (!$stock_total) {
                $stock_total = 0;
            }

            $invtcategory[$key]['stock_total'] = intval($stock_total);
            $total += intval($stock_total);
        }

        $allcategory = array(
            'item_category_id' => 1,
            'company_id' => 2,
            'item_category_name' => 'Semua',
            'item_category_code' => 'Semua',
            'item_category_remark' => 'Semua',
            'stock_total' => $stock_total,
        );

        $invtcategory[] = $allcategory;

        $invtcategory = $invtcategory->sortBy('item_category_id')->values()->all();

        if ($invtcategory) {
            return response([
                'data' => $invtcategory
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Ditemukan'
            ], 401);
        }
    }

    public function getInvtItemUnit(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required|string',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $invtunit = InvtItemUnit::select('*')
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->orderBy('item_unit_name', 'ASC')
            ->get();

        if ($invtunit) {
            return response([
                'data' => $invtunit
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Ditemukan'
            ], 401);
        }

    }

    public function getInvtItem(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'item_category_id' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        if ($fields['item_category_id'] != 1) {
            $invitem = InvtItem::select('*')
                ->where('company_id', $company_id['company_id'])
                ->where('item_category_id', $fields['item_category_id'])
                ->where('data_state', 0)
                ->orderBy('item_name', 'ASC')
                ->get();
        } else {
            $invitem = InvtItem::select('*')
                ->where('company_id', $company_id['company_id'])
                ->where('data_state', 0)
                ->orderBy('item_name', 'ASC')
                ->get();
        }

        foreach ($invitem as $key => $val) {
            $stock_total = InvtItemStock::where('item_id', $val['item_id'])
                ->where('data_state', 0)
                ->sum('last_balance');

            if (!$stock_total) {
                $stock_total = 0;
            }

            $invitem[$key]['stock_total'] = intval($stock_total);
        }

        if ($invitem) {
            return response([
                'data' => $invitem
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Ditemukan'
            ], 401);
        }
    }

    public function getInvtItemAll(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $invitem = InvtItem::select('*')
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->where('item_category_id', 24)
            ->orWhere('item_category_id', 25)
            ->orderBy('item_name', 'ASC')
            ->get();

        foreach ($invitem as $key => $val) {
            $stock_total = InvtItemStock::where('item_id', $val['item_id'])
                ->where('data_state', 0)
                ->sum('last_balance');

            if (!$stock_total) {
                $stock_total = 0;
            }

            $invitem[$key]['stock_total'] = $stock_total;
        }

        if ($invitem) {
            return response([
                'data' => $invitem
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Ditemukan'
            ], 401);
        }
    }

    public function getInvtItemDetail(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'item_id' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $invitem = InvtItem::select('*')
            ->where('company_id', $company_id['company_id'])
            ->where('item_id', $fields['item_id'])
            ->where('data_state', 0)
            ->first();

        if ($invitem) {
            return response([
                'data' => $invitem
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Ditemukan'
            ], 401);
        }

    }

    public function insertInvtItemUnit(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'item_unit_code' => 'required',
            'item_unit_name' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $data = array(
            'item_unit_code' => $fields['item_unit_code'],
            'item_unit_name' => $fields['item_unit_name'],
            'item_unit_remark' => $request->item_unit_remark,
            'company_id' => $company_id['company_id']
        );

        if (InvtItemUnit::create($data)) {
            return response([
                'message' => 'Data Berhasil Disimpan'
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Berhasil Disimpan'
            ], 401);
        }
    }

    public function insertInvtItemCategory(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'item_category_code' => 'required',
            'item_category_name' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $data = array(
            'item_category_code' => $fields['item_category_code'],
            'item_category_name' => $fields['item_category_name'],
            'item_category_remark' => $request->item_category_remark,
            'company_id' => $company_id['company_id']
        );

        if (InvtItemCategory::create($data)) {
            return response([
                'message' => 'Data Berhasil Disimpan'
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Berhasil Disimpan'
            ], 401);
        }
    }

    public function insertInvtItem(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'item_category_id' => 'required',
            'item_unit_id' => 'required',
            'item_name' => 'required',
            'item_code' => 'required',
            'item_default_quantity' => 'required',
            'item_unit_cost' => 'required',
            'item_unit_price' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $data = array(
            'item_category_id' => $fields['item_category_id'],
            'item_unit_id' => $fields['item_unit_id'],
            'item_name' => $fields['item_name'],
            'item_code' => $fields['item_code'],
            'item_default_quantity' => $fields['item_default_quantity'],
            'item_unit_cost' => $fields['item_unit_cost'],
            'item_unit_price' => $fields['item_unit_price'],
            'item_remark' => $request->item_remark,
            'created_id' => $fields['user_id'],
            'company_id' => $company_id['company_id']
        );

        if (InvtItem::create($data)) {
            $warehouse = InvtWarehouse::select('warehouse_id')
                ->where('company_id', $company_id['company_id'])
                ->get();

            $lastitem = InvtItem::select('item_id')
                ->where('created_id', $fields['user_id'])
                ->orderBy('item_id', 'DESC')
                ->first();

            foreach ($warehouse as $key => $val) {
                $data_stock = array(
                    'company_id' => $company_id['company_id'],
                    'warehouse_id' => $val['warehouse_id'],
                    'item_id' => $lastitem['item_id'],
                    'item_unit_id' => $fields['item_unit_id'],
                    'item_category_id' => $fields['item_category_id'],
                    'last_balance' => 0,
                    'created_id' => $fields['user_id'],
                );
                InvtItemStock::create($data_stock);
            }

            return response([
                'message' => 'Data Berhasil Disimpan'
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Berhasil Disimpan'
            ], 401);
        }
    }

    public function updateInvtItem(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'item_id' => 'required',
            'item_category_id' => 'required',
            'item_unit_id' => 'required',
            'item_name' => 'required',
            'item_code' => 'required',
            'item_default_quantity' => 'required',
            'item_unit_cost' => 'required',
            'item_unit_price' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $item = InvtItem::findOrFail($fields['item_id']);

        $item->item_category_id = $fields['item_category_id'];
        $item->item_unit_id = $fields['item_unit_id'];
        $item->item_name = $fields['item_name'];
        $item->item_code = $fields['item_code'];
        $item->item_default_quantity = $fields['item_default_quantity'];
        $item->item_unit_cost = $fields['item_unit_cost'];
        $item->item_unit_price = $fields['item_unit_price'];
        $item->item_remark = $request->item_remark;

        if ($item->save()) {
            return response([
                'message' => 'Data Berhasil Disimpan'
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Berhasil Disimpan'
            ], 401);
        }
    }

    public function insertSalesInvoice(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'items' => 'required',
            'subtotal_amount' => 'required',
            'subtotal_item' => 'required',
            'discount_percentage_total' => 'required',
            'discount_amount_total' => 'required',
            'ppn_percentage_total' => 'required',
            'ppn_amount_total' => 'required',
            'total_amount' => 'required',
            'paid_amount' => 'required',
            'index_button' => 'required',
        ]);
        $description = $request->descriptions;

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $disc_percentage = 0;
        if ($request->discount_percentage_total > 0 && $request->discount_percentage_total != '' && $request->discount_percentage_total != null) {
            $disc_percentage = $request->discount_percentage_total;
        }

        if ($fields['index_button'] <= 8) {
            $payment_method = 0;
        } else if ($fields['index_button'] == 9) {
            $payment_method = 1;
        } else if ($fields['index_button'] == 10) {
            $payment_method = 2;
        } else if ($fields['index_button'] == 11) {
            $payment_method = 3;
        } else if ($fields['index_button'] == 12) {
            $payment_method = 4;
        }

        $data = array(
            'sales_invoice_date' => date("Y-m-d"),
            'subtotal_amount' => $fields['subtotal_amount'],
            'subtotal_item' => $fields['subtotal_item'],
            'discount_percentage_total' => $disc_percentage,
            'discount_amount_total' => $fields['discount_amount_total'],
            'ppn_percentage_total' => $fields['ppn_percentage_total'],
            'ppn_amount_total' => $fields['ppn_amount_total'],
            'total_amount' => $fields['total_amount'],
            'paid_amount' => $fields['paid_amount'],
            'change_amount' => $fields['paid_amount'] - $fields['total_amount'],
            'table_no' => $request->table_no,
            'created_id' => $fields['user_id'],
            'payment_method' => $payment_method,
            'company_id' => $company_id['company_id']
        );

        if (SalesInvoice::create($data)) {
            $salesinvoicelast = SalesInvoice::where('created_id', $fields['user_id'])->orderBy('sales_invoice_id', 'DESC')->first();
            foreach ($fields['items'] as $key => $val) {
                $item = InvtItem::where('item_id', $val['item_id'])->first();
                $data_item = array(
                    'sales_invoice_id' => $salesinvoicelast['sales_invoice_id'],
                    'item_category_id' => $item['item_category_id'],
                    'item_unit_id' => $item['item_unit_id'],
                    'item_id' => $val['item_id'],
                    'quantity' => $val['quantity'],
                    'item_unit_price' => $val['item_unit_price'],
                    'subtotal_amount' => $val['item_unit_price'] * $val['quantity'],
                    'subtotal_amount_after_discount' => $val['item_unit_price'] * $val['quantity'],
                    'discount_percentage' => 0,
                    'discount_amount' => 0,
                    'ppn_percentage' => $fields['ppn_percentage_total'],
                    'ppn_amount' => $fields['ppn_percentage_total'] / 100 * $val['item_unit_price'] * $val['quantity'],
                    'created_id' => $fields['user_id'],
                    'company_id' => $company_id['company_id']
                );

                if (SalesInvoiceItem::create($data_item)) {
                    $itemstock = InvtItemStock::where('item_id', $data_item['item_id'])->first();
                    if ($itemstock) {
                        $itemstock->last_balance = $itemstock['last_balance'] - $data_item['quantity'];
                        $itemstock->save();
                    } else {
                        // $warehouse = InvtWarehouse::select('warehouse_id')
                        //     ->where('company_id', $company_id['company_id'])
                        //     ->first();

                        $data_stock = array(
                            'company_id' => $company_id['company_id'],
                            'warehouse_id' => $warehouse['warehouse_id'],
                            'item_id' => $val['item_id'],
                            'item_unit_id' => $item['item_unit_id'],
                            'item_category_id' => $item['item_category_id'],
                            'last_balance' => ($data_item['quantity']) * -1,
                            'created_id' => $fields['user_id'],
                        );
                        InvtItemStock::create($data_stock);
                        $data_stock = array(
                            'company_id' => $company_id['company_id'],
                            // 'warehouse_id' => $warehouse['warehouse_id'],
                            'item_id' => $val['item_id'],
                            'item_unit_id' => $item['item_unit_id'],
                            'item_category_id' => $item['item_category_id'],
                            'last_balance' => ($data_item['quantity']) * -1,
                            'created_id' => $fields['user_id'],
                        );
                        InvtItemStock::create($data_stock);
                    }
                } else {
                    return response([
                        'message' => 'Data Tidak Berhasil Disimpan'
                    ], 401);
                }
            }

            foreach ($request->descriptions as $key => $val) {
                $item = SalesInvoiceItem::where('item_id', $val['item_id'])
                    ->where('sales_invoice_id', $salesinvoicelast['sales_invoice_id'])
                    ->first();

                $item->item_remark = $val['description'];
                $item->save();
            }

            $transaction_module_code = 'PJL';
            $transaction_module_id = $this->getTransactionModuleID($transaction_module_code);

            $journal = array(
                'company_id' => $company_id['company_id'],
                'journal_voucher_status' => 1,
                'journal_voucher_description' => $this->getTransactionModuleName($transaction_module_code),
                'journal_voucher_title' => $this->getTransactionModuleName($transaction_module_code),
                'transaction_module_id' => $transaction_module_id,
                'transaction_module_code' => $transaction_module_code,
                'journal_voucher_date' => $data['sales_invoice_date'],
                'journal_voucher_period' => date('Ym'),
                'updated_id' => $fields['user_id'],
                'created_id' => $fields['user_id']
            );

            // if (JournalVoucher::create($journal)) {
            //     $journal_voucher_id = JournalVoucher::where('company_id', $company_id['company_id'])
            //         ->orderBy('created_at', 'DESC')
            //         ->first();

            //     $account_setting_name = 'sales_cash_account';
            //     $account_id = $this->getAccountId($account_setting_name, $company_id['company_id']);
            //     $account_status = $this->getAccountStatus($account_setting_name, $company_id['company_id']);
            //     $account_default_status = $this->getAccountDefaultStatus($account_id);
            //     if ($account_status == 0) {
            //         $debit_ammount = $fields['total_amount'];
            //         $credit_ammount = 0;
            //     } else {
            //         $credit_ammount = $fields['total_amount'];
            //         $debit_ammount = 0;
            //     }
            //     $journal_debit = array(
            //         'company_id' => $company_id['company_id'],
            //         'journal_voucher_id' => $journal_voucher_id['journal_voucher_id'],
            //         'account_id' => $account_id,
            //         'journal_voucher_amount' => $fields['total_amount'],
            //         'account_id_default_status' => $account_default_status,
            //         'account_id_status' => $account_status,
            //         'journal_voucher_debit_amount' => $debit_ammount,
            //         'journal_voucher_credit_amount' => $credit_ammount,
            //         'updated_id' => $fields['user_id'],
            //         'created_id' => $fields['user_id']
            //     );
            //     JournalVoucherItem::create($journal_debit);

            //     $account_setting_name = 'sales_account';
            //     $account_id = $this->getAccountId($account_setting_name, $company_id['company_id']);
            //     $account_status = $this->getAccountStatus($account_setting_name, $company_id['company_id']);
            //     $account_default_status = $this->getAccountDefaultStatus($account_id);
            //     if ($account_status == 0) {
            //         $debit_ammount = $fields['total_amount'];
            //         $credit_ammount = 0;
            //     } else {
            //         $credit_ammount = $fields['total_amount'];
            //         $debit_ammount = 0;
            //     }
            //     $journal_credit = array(
            //         'company_id' => $company_id['company_id'],
            //         'journal_voucher_id' => $journal_voucher_id['journal_voucher_id'],
            //         'account_id' => $account_id,
            //         'journal_voucher_amount' => $fields['total_amount'],
            //         'account_id_default_status' => $account_default_status,
            //         'account_id_status' => $account_status,
            //         'journal_voucher_debit_amount' => $debit_ammount,
            //         'journal_voucher_credit_amount' => $credit_ammount,
            //         'updated_id' => $fields['user_id'],
            //         'created_id' => $fields['user_id']
            //     );
            //     JournalVoucherItem::create($journal_credit);

            //     // $account_setting_name = 'sales_ppn_account';
            //     // $account_id = $this->getAccountId($account_setting_name, $company_id['company_id']);
            //     // $account_status = $this->getAccountStatus($account_setting_name, $company_id['company_id']);
            //     // $account_default_status = $this->getAccountDefaultStatus($account_id);
            //     // if($account_status == 0){
            //     //     $debit_ammount = $fields['ppn_amount_total'];
            //     //     $credit_ammount = 0;
            //     // }else{
            //     //     $credit_ammount = $fields['ppn_amount_total'];
            //     //     $debit_ammount = 0;
            //     // }
            //     // $journal_credit = array(
            //     //     'company_id'                    => $company_id['company_id'],
            //     //     'journal_voucher_id'            => $journal_voucher_id['journal_voucher_id'],
            //     //     'account_id'                    => $account_id,
            //     //     'journal_voucher_amount'        => $fields['ppn_amount_total'],
            //     //     'account_id_default_status'     => $account_default_status,
            //     //     'account_id_status'             => $account_status,
            //     //     'journal_voucher_debit_amount'  => $debit_ammount,
            //     //     'journal_voucher_credit_amount' => $credit_ammount,
            //     //     'updated_id'                    => $fields['user_id'],
            //     //     'created_id'                    => $fields['user_id']
            //     // );
            //     // JournalVoucherItem::create($journal_credit);
            // }
        } else {
            return response([
                'message' => 'Data Tidak Berhasil Disimpan'
            ], 401);
        }

        return response([
            'message' => 'Data Berhasil Disimpan'
        ], 201);

    }

    public function insertSalesInvoiceRecipe(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'items' => 'required',
            'subtotal_amount' => 'required',
            'subtotal_item' => 'required',
            'discount_percentage_total' => 'required',
            'discount_amount_total' => 'required',
            'ppn_percentage_total' => 'required',
            'ppn_amount_total' => 'required',
            'total_amount' => 'required',
            'paid_amount' => 'required',
            'index_button' => 'required',
        ]);
        $description = $request->descriptions;

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $disc_percentage = 0;
        if ($request->discount_percentage_total > 0 && $request->discount_percentage_total != '' && $request->discount_percentage_total != null) {
            $disc_percentage = $request->discount_percentage_total;
        }

        if ($fields['index_button'] <= 8) {
            $payment_method = 1;
        } else if ($fields['index_button'] == 9) {
            $payment_method = 3;
        } else if ($fields['index_button'] == 10) {
            $payment_method = 4;
        } else if ($fields['index_button'] == 11) {
            $payment_method = 5;
        } else if ($fields['index_button'] == 12) {
            $payment_method = 6;
        }

        $data = array(
            'sales_invoice_date' => date("Y-m-d"),
            'subtotal_amount' => $fields['subtotal_amount'],
            'subtotal_item' => $fields['subtotal_item'],
            'discount_percentage_total' => $disc_percentage,
            'discount_amount_total' => $fields['discount_amount_total'],
            'ppn_percentage_total' => $fields['ppn_percentage_total'],
            'ppn_amount_total' => $fields['ppn_amount_total'],
            'total_amount' => $fields['total_amount'],
            'paid_amount' => $fields['paid_amount'],
            'change_amount' => $fields['paid_amount'] - $fields['total_amount'],
            'table_no' => $request->table_no,
            'created_id' => $fields['user_id'],
            'payment_method' => $payment_method,
            'company_id' => $company_id['company_id']
        );
        try {
            DB::beginTransaction();

            if (SalesInvoice::create($data)) {
                $salesinvoicelast = SalesInvoice::orderBy('created_at', 'DESC')->where('company_id', Auth::user()->company_id)->first();
                foreach ($fields['items'] as $key => $val) {
                    $item = InvtItem::where('item_id', $val['item_id'])->first();
                    $data_item = array(
                        'sales_invoice_id' => $salesinvoicelast['sales_invoice_id'],
                        'item_category_id' => $item['item_category_id'],
                        'item_unit_id' => $item['item_unit_id'],
                        'item_id' => $val['item_id'],
                        'quantity' => $val['quantity'],
                        'item_unit_price' => $val['item_unit_price'],
                        'subtotal_amount' => $val['item_unit_price'] * $val['quantity'],
                        'subtotal_amount_after_discount' => $val['item_unit_price'] * $val['quantity'],
                        'discount_percentage' => 0,
                        'discount_amount' => 0,
                        'ppn_percentage' => $fields['ppn_percentage_total'],
                        'ppn_amount' => $fields['ppn_percentage_total'] / 100 * $val['item_unit_price'] * $val['quantity'],
                        'created_id' => $fields['user_id'],
                        'company_id' => $company_id['company_id']
                    );

                    if (SalesInvoiceItem::create($data_item)) {
                        $itemrecipe = CoreRecipe::where('item_menu_id', $data_item['item_id'])
                            ->where('data_state', 0)
                            ->get();

                        if (count($itemrecipe) > 0) {
                            foreach ($itemrecipe as $keyy => $vall) {
                                $itemstock = InvtItemStock::where('item_id', $vall['item_id'])
                                    ->where('item_unit_id', $vall['item_unit_id'])
                                    ->first();

                                if ($itemstock) {
                                    $itemstock->last_balance = $itemstock['last_balance'] - ($vall['quantity'] * $data_item['quantity']);
                                    $itemstock->save();
                                } else {
                                    $warehouse = InvtWarehouse::select('warehouse_id')
                                        ->where('company_id', $company_id['company_id'])
                                        ->first();

                                    $itemcategory = InvtItem::select('item_category_id')
                                        ->where('item_id', $vall['item_id'])
                                        ->first();

                                    $data_stock = array(
                                        'company_id' => $company_id['company_id'],
                                        'warehouse_id' => $warehouse['warehouse_id'],
                                        'item_id' => $vall['item_id'],
                                        'item_unit_id' => $vall['item_unit_id'],
                                        'item_category_id' => $itemcategory['item_category_id'],
                                        'last_balance' => ($vall['quantity'] * $data_item['quantity']) * -1,
                                        'created_id' => $fields['user_id'],
                                    );
                                    InvtItemStock::create($data_stock);
                                }
                            }
                        } else {
                            $itemstock = InvtItemStock::where('item_id', $data_item['item_id'])->first();
                            if ($itemstock) {
                                $itemstock->last_balance = $itemstock['last_balance'] - $data_item['quantity'];
                                $itemstock->save();
                            } else {
                                $warehouse = InvtWarehouse::select('warehouse_id')
                                    ->where('company_id', $company_id['company_id'])
                                    ->first();

                                $data_stock = array(
                                    'company_id' => $company_id['company_id'],
                                    'warehouse_id' => $warehouse['warehouse_id'],
                                    'item_id' => $val['item_id'],
                                    'item_unit_id' => $item['item_unit_id'],
                                    'item_category_id' => $item['item_category_id'],
                                    'last_balance' => ($data_item['quantity']) * -1,
                                    'created_id' => $fields['user_id'],
                                );
                                InvtItemStock::create($data_stock);
                            }
                        }
                    } else {
                        return response([
                            'message' => 'Data Tidak Berhasil Disimpan'
                        ], 401);
                    }
                }

                foreach ($request->descriptions as $key => $val) {
                    $item = SalesInvoiceItem::where('item_id', $val['item_id'])
                        ->where('sales_invoice_id', $salesinvoicelast['sales_invoice_id'])
                        ->first();

                    $item->item_remark = $val['description'];
                    $item->save();
                }

                $transaction_module_code = 'PJL';
                $transaction_module_id = $this->getTransactionModuleID($transaction_module_code);

                $sales_invoice_id = SalesInvoice::orderBy('created_at', 'DESC')->where('company_id', Auth::user()->company_id)->first();


                $journal = array(
                    'company_id' => $company_id['company_id'],
                    'journal_voucher_status' => 1,
                    'journal_voucher_description' => $this->getTransactionModuleName($transaction_module_code) . " Tunai " . $salesinvoicelast['sales_invoice_no'],
                    'journal_voucher_title' => $this->getTransactionModuleName($transaction_module_code) . " Tunai " . $salesinvoicelast['sales_invoice_no'],
                    'transaction_module_id' => $transaction_module_id,
                    'transaction_module_code' => $transaction_module_code,
                    'transaction_journal_no' => $salesinvoicelast['sales_invoice_no'],
                    'invoice_id' => $salesinvoicelast['sales_invoice_id'],
                    'journal_voucher_date' => $data['sales_invoice_date'],
                    'journal_voucher_period' => date('Ym'),
                    'updated_id' => $fields['user_id'],
                    'created_id' => $fields['user_id']
                );

                if (JournalVoucher::create($journal)) {
                    $journal_voucher_id = JournalVoucher::where('company_id', $company_id['company_id'])
                        ->orderBy('created_at', 'DESC')
                        ->first();

                    $account_setting_name = 'sales_cash_account';
                    $account_id = $this->getAccountId($account_setting_name, $company_id['company_id']);
                    $account_status = $this->getAccountStatus($account_setting_name, $company_id['company_id']);
                    $account_default_status = $this->getAccountDefaultStatus($account_id);
                    if ($account_status == 0) {
                        $debit_ammount = $fields['total_amount'];
                        $credit_ammount = 0;
                    } else {
                        $credit_ammount = $fields['total_amount'];
                        $debit_ammount = 0;
                    }
                    $journal_debit = array(
                        'company_id' => $company_id['company_id'],
                        'journal_voucher_id' => $journal_voucher_id['journal_voucher_id'],
                        'account_id' => $account_id,
                        'journal_voucher_amount' => $fields['total_amount'],
                        'account_id_default_status' => $account_default_status,
                        'account_id_status' => $account_status,
                        'journal_voucher_debit_amount' => $debit_ammount,
                        'journal_voucher_credit_amount' => $credit_ammount,
                        'updated_id' => $fields['user_id'],
                        'created_id' => $fields['user_id']
                    );
                    JournalVoucherItem::create($journal_debit);

                    $account_setting_name = 'sales_account';
                    $account_id = $this->getAccountId($account_setting_name, $company_id['company_id']);
                    $account_status = $this->getAccountStatus($account_setting_name, $company_id['company_id']);
                    $account_default_status = $this->getAccountDefaultStatus($account_id);
                    if ($account_status == 0) {
                        $debit_ammount = $fields['total_amount'];
                        $credit_ammount = 0;
                    } else {
                        $credit_ammount = $fields['total_amount'];
                        $debit_ammount = 0;
                    }
                    $journal_credit = array(
                        'company_id' => $company_id['company_id'],
                        'journal_voucher_id' => $journal_voucher_id['journal_voucher_id'],
                        'account_id' => $account_id,
                        'journal_voucher_amount' => $fields['total_amount'],
                        'account_id_default_status' => $account_default_status,
                        'account_id_status' => $account_status,
                        'journal_voucher_debit_amount' => $debit_ammount,
                        'journal_voucher_credit_amount' => $credit_ammount,
                        'updated_id' => $fields['user_id'],
                        'created_id' => $fields['user_id']
                    );
                    JournalVoucherItem::create($journal_credit);
                }
            }
            DB::commit();
            return response(['message' => 'Data Berhasil Disimpan'], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response(['message' => 'Data Tidak Berhasil Disimpan', 'eror' => $e], 400);
        }

    }

    public function getTransactionModuleID($transaction_module_code)
    {
        $data = PreferenceTransactionModule::where('transaction_module_code', $transaction_module_code)->first();

        return $data['transaction_module_id'];
    }

    public function getTransactionModuleName($transaction_module_code)
    {
        $data = PreferenceTransactionModule::where('transaction_module_code', $transaction_module_code)->first();

        return $data['transaction_module_name'];
    }

    public function getAccountStatus($account_setting_name, $company_id)
    {
        $data = AcctAccountSetting::where('company_id', $company_id)
            ->where('account_setting_name', $account_setting_name)
            ->first();

        return $data['account_setting_status'];
    }

    public function getAccountId($account_setting_name, $company_id)
    {
        $data = AcctAccountSetting::where('company_id', $company_id)
            ->where('account_setting_name', $account_setting_name)
            ->first();

        return $data['account_id'];
    }

    public function getAccountDefaultStatus($account_id)
    {
        $data = AcctAccount::where('account_id', $account_id)
            ->first();

        return $data['account_default_status'];
    }

    public function insertSaveSalesInvoice(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'items' => 'required',
            'subtotal_amount' => 'required',
            'subtotal_item' => 'required',
            'discount_percentage_total' => 'required',
            'discount_amount_total' => 'required',
            'ppn_percentage_total' => 'required',
            'ppn_amount_total' => 'required',
            'total_amount' => 'required',
        ]);
        $description = $request->descriptions;

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $disc_percentage = 0;
        if ($request->discount_percentage_total > 0 && $request->discount_percentage_total != '' && $request->discount_percentage_total != null) {
            $disc_percentage = $request->discount_percentage_total;
        }

        $data = array(
            'sales_invoice_date' => date("Y-m-d"),
            'subtotal_amount' => $fields['subtotal_amount'],
            'subtotal_item' => $fields['subtotal_item'],
            'discount_percentage_total' => $disc_percentage,
            'discount_amount_total' => $fields['discount_amount_total'],
            'ppn_percentage_total' => $fields['ppn_percentage_total'],
            'ppn_amount_total' => $fields['ppn_amount_total'],
            'total_amount' => $fields['total_amount'],
            'paid_amount' => 0,
            'table_no' => $request->table_no,
            'change_amount' => 0,
            'created_id' => $fields['user_id'],
            'company_id' => $company_id['company_id']
        );

        if (SalesInvoice::create($data)) {
            $salesinvoicelast = SalesInvoice::where('created_id', $fields['user_id'])->orderBy('sales_invoice_id', 'DESC')->first();
            foreach ($fields['items'] as $key => $val) {
                $item = InvtItem::where('item_id', $val['item_id'])->first();
                $data_item = array(
                    'sales_invoice_id' => $salesinvoicelast['sales_invoice_id'],
                    'item_category_id' => $item['item_category_id'],
                    'item_unit_id' => $item['item_unit_id'],
                    'item_id' => $val['item_id'],
                    'quantity' => $val['quantity'],
                    'item_unit_price' => $val['item_unit_price'],
                    'subtotal_amount' => $val['item_unit_price'] * $val['quantity'],
                    'subtotal_amount_after_discount' => $val['item_unit_price'] * $val['quantity'],
                    'discount_percentage' => 0,
                    'discount_amount' => 0,
                    'ppn_percentage' => $fields['ppn_percentage_total'],
                    'ppn_amount' => $fields['ppn_percentage_total'] / 100 * $val['item_unit_price'] * $val['quantity'],
                    'created_id' => $fields['user_id'],
                    'company_id' => $company_id['company_id']
                );

                if (SalesInvoiceItem::create($data_item)) {
                    $itemstock = InvtItemStock::where('item_id', $data_item['item_id'])->first();
                    if ($itemstock) {
                        $itemstock->last_balance = $itemstock['last_balance'] - $data_item['quantity'];
                        $itemstock->save();
                    } else {
                        $warehouse = InvtWarehouse::select('warehouse_id')
                            ->where('company_id', $company_id['company_id'])
                            ->first();

                        $data_stock = array(
                            'company_id' => $company_id['company_id'],
                            'warehouse_id' => $warehouse['warehouse_id'],
                            'item_id' => $val['item_id'],
                            'item_unit_id' => $item['item_unit_id'],
                            'item_category_id' => $item['item_category_id'],
                            'last_balance' => ($data_item['quantity']) * -1,
                            'created_id' => $fields['user_id'],
                        );
                        InvtItemStock::create($data_stock);
                    }
                } else {
                    return response([
                        'message' => 'Data Tidak Berhasil Disimpan'
                    ], 401);
                }
            }

            foreach ($request->descriptions as $key => $val) {
                $item = SalesInvoiceItem::where('item_id', $val['item_id'])
                    ->where('sales_invoice_id', $salesinvoicelast['sales_invoice_id'])
                    ->first();

                $item->item_remark = $val['description'];
                $item->save();
            }
        } else {
            return response([
                'message' => 'Data Tidak Berhasil Disimpan'
            ], 401);
        }


        return response([
            'message' => 'Data Berhasil Disimpan'
        ], 201);

    }

    public function insertSaveSalesInvoiceRecipe(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'items' => 'required',
            'subtotal_amount' => 'required',
            'subtotal_item' => 'required',
            'discount_percentage_total' => 'required',
            'discount_amount_total' => 'required',
            'ppn_percentage_total' => 'required',
            'ppn_amount_total' => 'required',
            'total_amount' => 'required',
        ]);
        $description = $request->descriptions;

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $disc_percentage = 0;
        if ($request->discount_percentage_total > 0 && $request->discount_percentage_total != '' && $request->discount_percentage_total != null) {
            $disc_percentage = $request->discount_percentage_total;
        }

        $data = array(
            'sales_invoice_date' => date("Y-m-d"),
            'subtotal_amount' => $fields['subtotal_amount'],
            'subtotal_item' => $fields['subtotal_item'],
            'discount_percentage_total' => $disc_percentage,
            'discount_amount_total' => $fields['discount_amount_total'],
            'ppn_percentage_total' => $fields['ppn_percentage_total'],
            'ppn_amount_total' => $fields['ppn_amount_total'],
            'total_amount' => $fields['total_amount'],
            'paid_amount' => 0,
            'table_no' => $request->table_no,
            'change_amount' => 0,
            'created_id' => $fields['user_id'],
            'company_id' => $company_id['company_id']
        );

        if (SalesInvoice::create($data)) {
            $salesinvoicelast = SalesInvoice::where('created_id', $fields['user_id'])->orderBy('sales_invoice_id', 'DESC')->first();
            foreach ($fields['items'] as $key => $val) {
                $item = InvtItem::where('item_id', $val['item_id'])->first();
                $data_item = array(
                    'sales_invoice_id' => $salesinvoicelast['sales_invoice_id'],
                    'item_category_id' => $item['item_category_id'],
                    'item_unit_id' => $item['item_unit_id'],
                    'item_id' => $val['item_id'],
                    'quantity' => $val['quantity'],
                    'item_unit_price' => $val['item_unit_price'],
                    'subtotal_amount' => $val['item_unit_price'] * $val['quantity'],
                    'subtotal_amount_after_discount' => $val['item_unit_price'] * $val['quantity'],
                    'discount_percentage' => 0,
                    'discount_amount' => 0,
                    'ppn_percentage' => $fields['ppn_percentage_total'],
                    'ppn_amount' => $fields['ppn_percentage_total'] / 100 * $val['item_unit_price'] * $val['quantity'],
                    'created_id' => $fields['user_id'],
                    'company_id' => $company_id['company_id']
                );

                if (SalesInvoiceItem::create($data_item)) {
                    $itemrecipe = CoreRecipe::where('item_menu_id', $data_item['item_id'])
                        ->where('data_state', 0)
                        ->get();

                    if (count($itemrecipe) > 0) {
                        foreach ($itemrecipe as $keyy => $vall) {
                            $itemstock = InvtItemStock::where('item_id', $vall['item_id'])
                                ->where('item_unit_id', $vall['item_unit_id'])
                                ->first();

                            if ($itemstock) {
                                $itemstock->last_balance = $itemstock['last_balance'] - ($vall['quantity'] * $data_item['quantity']);
                                $itemstock->save();
                            } else {
                                $warehouse = InvtWarehouse::select('warehouse_id')
                                    ->where('company_id', $company_id['company_id'])
                                    ->first();

                                $itemcategory = InvtItem::select('item_category_id')
                                    ->where('item_id', $vall['item_id'])
                                    ->first();

                                $data_stock = array(
                                    'company_id' => $company_id['company_id'],
                                    'warehouse_id' => $warehouse['warehouse_id'],
                                    'item_id' => $vall['item_id'],
                                    'item_unit_id' => $vall['item_unit_id'],
                                    'item_category_id' => $itemcategory['item_category_id'],
                                    'last_balance' => ($vall['quantity'] * $data_item['quantity']) * -1,
                                    'created_id' => $fields['user_id'],
                                );
                                InvtItemStock::create($data_stock);
                            }
                        }
                    } else {
                        $itemstock = InvtItemStock::where('item_id', $data_item['item_id'])->first();
                        if ($itemstock) {
                            $itemstock->last_balance = $itemstock['last_balance'] - $data_item['quantity'];
                            $itemstock->save();
                        } else {
                            $warehouse = InvtWarehouse::select('warehouse_id')
                                ->where('company_id', $company_id['company_id'])
                                ->first();

                            $data_stock = array(
                                'company_id' => $company_id['company_id'],
                                'warehouse_id' => $warehouse['warehouse_id'],
                                'item_id' => $val['item_id'],
                                'item_unit_id' => $item['item_unit_id'],
                                'item_category_id' => $item['item_category_id'],
                                'last_balance' => ($data_item['quantity']) * -1,
                                'created_id' => $fields['user_id'],
                            );
                            InvtItemStock::create($data_stock);
                        }
                    }
                } else {
                    return response([
                        'message' => 'Data Tidak Berhasil Disimpan'
                    ], 401);
                }
            }

            foreach ($request->descriptions as $key => $val) {
                $item = SalesInvoiceItem::where('item_id', $val['item_id'])
                    ->where('sales_invoice_id', $salesinvoicelast['sales_invoice_id'])
                    ->first();

                $item->item_remark = $val['description'];
                $item->save();
            }
        } else {
            return response([
                'message' => 'Data Tidak Berhasil Disimpan'
            ], 401);
        }


        return response([
            'message' => 'Data Berhasil Disimpan'
        ], 201);

    }

    public function paySavedSalesOrder(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'sales_invoice_id' => 'required',
            'discount_percentage_total' => 'required',
            'discount_amount_total' => 'required',
            'ppn_percentage_total' => 'required',
            'ppn_amount_total' => 'required',
            'paid_amount' => 'required',
            'index_button' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        if ($fields['index_button'] <= 8) {
            $payment_method = 1;
        } else if ($fields['index_button'] == 9) {
            $payment_method = 3;
        } else if ($fields['index_button'] == 10) {
            $payment_method = 4;
        } else if ($fields['index_button'] == 11) {
            $payment_method = 5;
        } else if ($fields['index_button'] == 12) {
            $payment_method = 6;
        }

        $salesinvoice = SalesInvoice::findOrFail($fields['sales_invoice_id']);
        $salesinvoice->discount_percentage_total = $fields['discount_percentage_total'];
        $salesinvoice->discount_amount_total = $fields['discount_amount_total'];
        $salesinvoice->total_amount = $salesinvoice->subtotal_amount - $fields['discount_amount_total'] + $fields['ppn_amount_total'];
        $salesinvoice->paid_amount = $fields['paid_amount'];
        $salesinvoice->change_amount = $fields['paid_amount'] - ($salesinvoice->subtotal_amount - $fields['discount_amount_total'] + $fields['ppn_amount_total']);
        $salesinvoice->payment_method = $payment_method;

        try {
            DB::beginTransaction();

            if ($salesinvoice->save()) {

                $transaction_module_code = 'PJL';
                $transaction_module_id = $this->getTransactionModuleID($transaction_module_code);

                $journal = array(
                    'company_id' => $company_id['company_id'],
                    'journal_voucher_status' => 1,
                    'journal_voucher_description' => $this->getTransactionModuleName($transaction_module_code) . " Tunai " . $salesinvoice['sales_invoice_no'],
                    'journal_voucher_title' => $this->getTransactionModuleName($transaction_module_code) . " Tunai " . $salesinvoice['sales_invoice_no'],
                    'transaction_module_id' => $transaction_module_id,
                    'transaction_module_code' => $transaction_module_code,
                    'transaction_journal_no' => $salesinvoice['sales_invoice_no'],
                    'invoice_id' => $salesinvoice['sales_invoice_id'],
                    'journal_voucher_date' => $salesinvoice['sales_invoice_date'],
                    'journal_voucher_period' => date('Ym'),
                    'updated_id' => $fields['user_id'],
                    'created_id' => $fields['user_id']
                );

                if (JournalVoucher::create($journal)) {
                    $journal_voucher_id = JournalVoucher::where('company_id', $company_id['company_id'])
                        ->orderBy('created_at', 'DESC')
                        ->first();

                    $account_setting_name = 'sales_cash_account';
                    $account_id = $this->getAccountId($account_setting_name, $company_id['company_id']);
                    $account_status = $this->getAccountStatus($account_setting_name, $company_id['company_id']);
                    $account_default_status = $this->getAccountDefaultStatus($account_id);
                    if ($account_status == 0) {
                        $debit_ammount = $salesinvoice['total_amount'];
                        $credit_ammount = 0;
                    } else {
                        $credit_ammount = $salesinvoice['total_amount'];
                        $debit_ammount = 0;
                    }
                    $journal_debit = array(
                        'company_id' => $company_id['company_id'],
                        'journal_voucher_id' => $journal_voucher_id['journal_voucher_id'],
                        'account_id' => $account_id,
                        'journal_voucher_amount' => $salesinvoice['total_amount'],
                        'account_id_default_status' => $account_default_status,
                        'account_id_status' => $account_status,
                        'journal_voucher_debit_amount' => $debit_ammount,
                        'journal_voucher_credit_amount' => $credit_ammount,
                        'updated_id' => $fields['user_id'],
                        'created_id' => $fields['user_id']
                    );
                    JournalVoucherItem::create($journal_debit);

                    $account_setting_name = 'sales_account';
                    $account_id = $this->getAccountId($account_setting_name, $company_id['company_id']);
                    $account_status = $this->getAccountStatus($account_setting_name, $company_id['company_id']);
                    $account_default_status = $this->getAccountDefaultStatus($account_id);
                    if ($account_status == 0) {
                        $debit_ammount = $salesinvoice['total_amount'];
                        $credit_ammount = 0;
                    } else {
                        $credit_ammount = $salesinvoice['total_amount'];
                        $debit_ammount = 0;
                    }
                    $journal_credit = array(
                        'company_id' => $company_id['company_id'],
                        'journal_voucher_id' => $journal_voucher_id['journal_voucher_id'],
                        'account_id' => $account_id,
                        'journal_voucher_amount' => $salesinvoice['total_amount'],
                        'account_id_default_status' => $account_default_status,
                        'account_id_status' => $account_status,
                        'journal_voucher_debit_amount' => $debit_ammount,
                        'journal_voucher_credit_amount' => $credit_ammount,
                        'updated_id' => $fields['user_id'],
                        'created_id' => $fields['user_id']
                    );
                    JournalVoucherItem::create($journal_credit);
                }
            }
            DB::commit();
            return response(['message' => 'Data Berhasil Disimpan'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response(['message' => 'Data Tidak Berhasil Disimpan', 'eror' => $e], 400);
        }
    }

    public function getUnpaidSalesListToday(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $salesinvoice = SalesInvoice::select('*')
            ->where('sales_invoice.company_id', $company_id['company_id'])
            ->where('sales_invoice.sales_invoice_date', date("Y-m-d"))
            ->where('sales_invoice.paid_amount', 0)
            ->where('sales_invoice.data_state', 0)
            ->orderBy('sales_invoice_id', 'DESC')
            ->get();

        $total_amount_today = 0;
        foreach ($salesinvoice as $key => $val) {

            $salesinvoiceitem = SalesInvoiceItem::select('invt_item.item_name')
                ->join('invt_item', 'sales_invoice_item.item_id', 'invt_item.item_id')
                ->where('sales_invoice_item.sales_invoice_id', $val['sales_invoice_id'])
                ->get();

            $no = 0;
            foreach ($salesinvoiceitem as $keyy => $vall) {
                if ($no == 0) {
                    $val->{"salesinvoiceitem"} = array();
                    $val->{"salesinvoiceitem_name"} = '';
                }
                $val->salesinvoiceitem = array_merge($val->salesinvoiceitem, [$vall]);
                $val->salesinvoiceitem_name = $val->salesinvoiceitem_name . $vall['item_name'] . ', ';
                $no++;
            }
            $val->salesinvoiceitem_name = substr($val->salesinvoiceitem_name, 0, -2);

            $total_amount_today += $val['total_amount'];
        }



        if ($salesinvoice) {
            return response([
                'data' => $salesinvoice,
                'total_amount_today' => $total_amount_today
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Ditemukan'
            ], 401);
        }
    }

    public function getEditSalesListToday(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'sales_invoice_id' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $salesinvoice = SalesInvoice::select('*')
            ->where('sales_invoice.company_id', $company_id['company_id'])
            ->where('sales_invoice.sales_invoice_date', date("Y-m-d"))
            ->where('sales_invoice.paid_amount', 0)
            ->where('sales_invoice.data_state', 0)
            ->orderBy('sales_invoice_id', 'DESC')
            ->get();

        $total_amount_today = 0;
        foreach ($salesinvoice as $key => $val) {

            $salesinvoiceitem = SalesInvoiceItem::select('invt_item.item_name')
                ->join('invt_item', 'sales_invoice_item.item_id', 'invt_item.item_id')
                ->where('sales_invoice_item.sales_invoice_id', $val['sales_invoice_id'])
                ->get();

            $no = 0;
            foreach ($salesinvoiceitem as $keyy => $vall) {
                if ($no == 0) {
                    $val->{"salesinvoiceitem"} = array();
                    $val->{"salesinvoiceitem_name"} = '';
                }
                $val->salesinvoiceitem = array_merge($val->salesinvoiceitem, [$vall]);
                $val->salesinvoiceitem_name = $val->salesinvoiceitem_name . $vall['item_name'] . ', ';
                $no++;
            }
            $val->salesinvoiceitem_name = substr($val->salesinvoiceitem_name, 0, -2);

            $total_amount_today += $val['total_amount'];
        }



        if ($salesinvoice) {
            return response([
                'data' => $salesinvoice,
                'total_amount_today' => $total_amount_today
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Ditemukan'
            ], 401);
        }
    }

    public function getPaidSalesListToday(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $salesinvoice = SalesInvoice::select('*')
            ->where('sales_invoice.company_id', $company_id['company_id'])
            ->where('sales_invoice.sales_invoice_date', date("Y-m-d"))
            ->where('sales_invoice.paid_amount', '!=', 0)
            ->where('sales_invoice.data_state', 0)
            ->orderBy('sales_invoice.updated_at', 'DESC')
            ->get();

        $total_amount_today = 0;
        foreach ($salesinvoice as $key => $val) {

            $salesinvoiceitem = SalesInvoiceItem::select('invt_item.item_name')
                ->join('invt_item', 'sales_invoice_item.item_id', 'invt_item.item_id')
                ->where('sales_invoice_item.sales_invoice_id', $val['sales_invoice_id'])
                ->get();

            $no = 0;
            foreach ($salesinvoiceitem as $keyy => $vall) {
                if ($no == 0) {
                    $val->{"salesinvoiceitem"} = array();
                    $val->{"salesinvoiceitem_name"} = '';
                }
                $val->salesinvoiceitem = array_merge($val->salesinvoiceitem, [$vall]);
                $val->salesinvoiceitem_name = $val->salesinvoiceitem_name . $vall['item_name'] . ', ';
                $no++;
            }
            $val->salesinvoiceitem_name = substr($val->salesinvoiceitem_name, 0, -2);

            $total_amount_today += $val['total_amount'];
        }

        if ($salesinvoice) {
            return response([
                'data' => $salesinvoice,
                'total_amount_today' => $total_amount_today
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Ditemukan'
            ], 401);
        }
    }

    public function getPaidSalesListMenuToday(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $salesinvoiceitem = SalesInvoiceItem::select('invt_item.item_name', DB::raw('SUM(sales_invoice_item.quantity) as `quantity`'), DB::raw('SUM(sales_invoice_item.subtotal_amount) as `subtotal_amount`'))
            ->join('invt_item', 'invt_item.item_id', 'sales_invoice_item.item_id')
            ->join('sales_invoice', 'sales_invoice.sales_invoice_id', 'sales_invoice_item.sales_invoice_id')
            ->where('sales_invoice.company_id', $company_id['company_id'])
            ->where('sales_invoice.sales_invoice_date', date("Y-m-d"))
            ->where('sales_invoice.paid_amount', '!=', 0)
            ->where('sales_invoice.data_state', 0)
            ->groupBy('invt_item.item_id')
            ->get();

        if ($salesinvoiceitem) {
            return response([
                'data' => $salesinvoiceitem
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Ditemukan'
            ], 401);
        }
    }

    public function getSavedSalesOrder(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'sales_invoice_id' => 'required',
        ]);

        $salesinvoiceitem = SalesInvoiceItem::select('invt_item.item_name', 'sales_invoice_item.*')
            ->join('invt_item', 'invt_item.item_id', 'sales_invoice_item.item_id')
            ->where('sales_invoice_item.sales_invoice_id', $fields['sales_invoice_id'])
            ->where('sales_invoice_item.data_state', 0)
            ->get();

        $salesinvoice = SalesInvoice::select('table_no', 'discount_percentage_total', 'discount_amount_total', 'ppn_percentage_total', 'ppn_amount_total')
            ->where('sales_invoice_id', $fields['sales_invoice_id'])
            ->first();

        if ($salesinvoiceitem) {
            return response([
                'data' => $salesinvoiceitem,
                'table_no' => $salesinvoice['table_no'],
                'discount_percentage_total' => $salesinvoice['discount_percentage_total'],
                'discount_amount_total' => $salesinvoice['discount_amount_total'],
                'ppn_percentage_total' => $salesinvoice['ppn_percentage_total'],
                'ppn_amount_total' => $salesinvoice['ppn_amount_total']
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Ditemukan'
            ], 401);
        }
    }

    public function insertCapitalMoney(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'capital_money_date' => 'required',
            'capital_money_amount' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $capitalmoney = CapitalMoney::select('*')
            ->where('capital_money_date', substr($fields['capital_money_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->first();

        if ($capitalmoney) {
            $capitalmoney->capital_money_amount = $fields['capital_money_amount'];
            if ($capitalmoney->save()) {
                return response([
                    'message' => 'Data Berhasil Disimpan',
                ], 201);
            } else {
                return response([
                    'message' => 'Data Tidak Berhasil Disimpan'
                ], 401);
            }
        } else {
            $data = array(
                'company_id' => $company_id['company_id'],
                'capital_money_amount' => $fields['capital_money_amount'],
                'capital_money_date' => $fields['capital_money_date'],
                'created_id' => $fields['user_id'],
            );

            if (CapitalMoney::create($data)) {
                return response([
                    'message' => 'Data Berhasil Disimpan',
                ], 201);
            } else {
                return response([
                    'message' => 'Data Tidak Berhasil Disimpan'
                ], 401);
            }
        }

    }

    public function insertExpenditure(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'account_id' => 'required',
            'expenditure_remark' => 'required',
            'expenditure_amount' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $data = array(
            'company_id' => $company_id['company_id'],
            'account_id' => $fields['account_id'],
            'expenditure_date' => date("Y-m-d"),
            'expenditure_amount' => $fields['expenditure_amount'],
            'expenditure_remark' => $fields['expenditure_remark'],
            'created_id' => $fields['user_id'],
        );

        if (Expenditure::create($data)) {
            $transaction_module_code = 'PGL';
            $transaction_module_id = $this->getTransactionModuleID($transaction_module_code);

            $journal = array(
                'company_id' => $company_id['company_id'],
                'journal_voucher_status' => 1,
                'journal_voucher_description' => $this->getTransactionModuleName($transaction_module_code),
                'journal_voucher_title' => $this->getTransactionModuleName($transaction_module_code),
                'transaction_module_id' => $transaction_module_id,
                'transaction_module_code' => $transaction_module_code,
                'journal_voucher_date' => $data['expenditure_date'],
                'journal_voucher_period' => date('Ym'),
                'updated_id' => $fields['user_id'],
                'created_id' => $fields['user_id']
            );

            if (JournalVoucher::create($journal)) {
                $journal_voucher_id = JournalVoucher::where('company_id', $company_id['company_id'])
                    ->orderBy('created_at', 'DESC')
                    ->first();

                $account_setting_name = 'expenditure_cash_account';
                $account_id = $this->getAccountId($account_setting_name, $company_id['company_id']);
                $account_status = $this->getAccountStatus($account_setting_name, $company_id['company_id']);
                $account_default_status = $this->getAccountDefaultStatus($account_id);
                if ($account_status == 0) {
                    $debit_ammount = $data['expenditure_amount'];
                    $credit_ammount = 0;
                } else {
                    $credit_ammount = $data['expenditure_amount'];
                    $debit_ammount = 0;
                }
                $journal_debit = array(
                    'company_id' => $company_id['company_id'],
                    'journal_voucher_id' => $journal_voucher_id['journal_voucher_id'],
                    'account_id' => $account_id,
                    'journal_voucher_amount' => $data['expenditure_amount'],
                    'account_id_default_status' => $account_default_status,
                    'account_id_status' => $account_status,
                    'journal_voucher_debit_amount' => $debit_ammount,
                    'journal_voucher_credit_amount' => $credit_ammount,
                    'updated_id' => $fields['user_id'],
                    'created_id' => $fields['user_id']
                );
                JournalVoucherItem::create($journal_debit);

                // $account_setting_name = 'expenditure_account';
                // $account_id = $this->getAccountId($account_setting_name, $company_id['company_id']);
                // $account_status = $this->getAccountStatus($account_setting_name, $company_id['company_id']);
                $account_id = $fields['account_id'];
                $account_status = 0;
                $account_default_status = $this->getAccountDefaultStatus($account_id);
                if ($account_status == 0) {
                    $debit_ammount = $data['expenditure_amount'];
                    $credit_ammount = 0;
                } else {
                    $credit_ammount = $data['expenditure_amount'];
                    $debit_ammount = 0;
                }
                $journal_credit = array(
                    'company_id' => $company_id['company_id'],
                    'journal_voucher_id' => $journal_voucher_id['journal_voucher_id'],
                    'account_id' => $account_id,
                    'journal_voucher_amount' => $data['expenditure_amount'],
                    'account_id_default_status' => $account_default_status,
                    'account_id_status' => $account_status,
                    'journal_voucher_debit_amount' => $debit_ammount,
                    'journal_voucher_credit_amount' => $credit_ammount,
                    'updated_id' => $fields['user_id'],
                    'created_id' => $fields['user_id']
                );
                JournalVoucherItem::create($journal_credit);
            }
            return response([
                'message' => 'Pengeluaran Berhasil Disimpan'
            ], 201);
        } else {
            return response([
                'message' => 'Pengeluaran Tidak Berhasil Disimpan'
            ], 401);
        }
    }

    public function getExpenditure(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $expenditure = Expenditure::select('*')
            ->where('company_id', $company_id['company_id'])
            ->where('expenditure_date', date("Y-m-d"))
            ->where('data_state', 0)
            ->get();

        $total_expenditure = 0;
        foreach ($expenditure as $key => $val) {
            $total_expenditure += $val['expenditure_amount'];
        }

        if ($expenditure) {
            return response([
                'data' => $expenditure,
                'total_expenditure' => $total_expenditure
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Ditemukan'
            ], 401);
        }
    }

    public function getDashboard(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'dashboard_start_date' => 'required',
            'dashboard_end_date' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $capitalmoney = CapitalMoney::select('*')
            ->where('capital_money_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('capital_money_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->get();

        $capitalmoneytoday = CapitalMoney::select('*')
            ->where('capital_money_date', date("Y-m-d"))
            ->where('company_id', $company_id['company_id'])
            ->first();

        $salesinvoicecash = SalesInvoice::select('*')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->where('payment_method', 1)
            ->get();

        $salesinvoicegopay = SalesInvoice::select('*')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->where('payment_method', 3)
            ->get();

        $salesinvoiceovo = SalesInvoice::select('*')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->where('payment_method', 4)
            ->get();

        $salesinvoiceshopeepay = SalesInvoice::select('*')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->where('payment_method', 5)
            ->get();

        $salesinvoiceothers = SalesInvoice::select('*')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->where('payment_method', 6)
            ->get();

        $salesinvoiceitem = SalesInvoiceItem::select('invt_item.item_name', DB::raw('SUM(sales_invoice_item.quantity) as `quantity`'), DB::raw('SUM(sales_invoice_item.subtotal_amount) as `subtotal_amount`'))
            ->join('invt_item', 'invt_item.item_id', 'sales_invoice_item.item_id')
            ->join('sales_invoice', 'sales_invoice.sales_invoice_id', 'sales_invoice_item.sales_invoice_id')
            ->where('sales_invoice.company_id', $company_id['company_id'])
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('sales_invoice.paid_amount', '!=', 0)
            ->where('sales_invoice.data_state', 0)
            ->groupBy('invt_item.item_id')
            ->get();

        $expenditure = Expenditure::select('*')
            ->where('expenditure_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('expenditure_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->get();

        $sales_total = 0;
        $sales_cash_subtotal = 0;
        $sales_gopay_subtotal = 0;
        $sales_ovo_subtotal = 0;
        $sales_shopeepay_subtotal = 0;
        $sales_others_subtotal = 0;
        $discount_total = 0;
        $ppn_total = 0;
        $sales_subtotal = 0;
        $sales_cash_total = 0;
        $sales_non_cash_total = 0;
        $expenditure_total = 0;
        $capital_money_total = 0;
        $capital_money_today = 0;

        foreach ($capitalmoney as $key => $val) {
            $capital_money_total += $val['capital_money_amount'];
        }

        foreach ($salesinvoicecash as $key => $val) {
            $discount_total += $val['discount_amount_total'];
            $ppn_total += $val['ppn_amount_total'];
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_cash_subtotal += $val['subtotal_amount'];
            $sales_cash_total += $val['total_amount'];
        }
        foreach ($salesinvoicegopay as $key => $val) {
            $discount_total += $val['discount_amount_total'];
            $ppn_total += $val['ppn_amount_total'];
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_gopay_subtotal += $val['subtotal_amount'];
            $sales_non_cash_total += $val['total_amount'];
        }
        foreach ($salesinvoiceshopeepay as $key => $val) {
            $discount_total += $val['discount_amount_total'];
            $ppn_total += $val['ppn_amount_total'];
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_shopeepay_subtotal += $val['subtotal_amount'];
            $sales_non_cash_total += $val['total_amount'];
        }
        foreach ($salesinvoiceovo as $key => $val) {
            $discount_total += $val['discount_amount_total'];
            $ppn_total += $val['ppn_amount_total'];
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_ovo_subtotal += $val['subtotal_amount'];
            $sales_non_cash_total += $val['total_amount'];
        }
        foreach ($salesinvoiceothers as $key => $val) {
            $discount_total += $val['discount_amount_total'];
            $ppn_total += $val['ppn_amount_total'];
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_others_subtotal += $val['subtotal_amount'];
            $sales_non_cash_total += $val['total_amount'];
        }

        foreach ($expenditure as $key => $val) {
            $expenditure_total += $val['expenditure_amount'];
        }

        if ($capitalmoneytoday) {
            $capital_money_today = $capitalmoneytoday['capital_money_amount'];
        }

        return response([
            'sales_cash_subtotal' => $sales_cash_subtotal,
            'sales_gopay_subtotal' => $sales_gopay_subtotal,
            'sales_ovo_subtotal' => $sales_ovo_subtotal,
            'sales_shopeepay_subtotal' => $sales_shopeepay_subtotal,
            'sales_others_subtotal' => $sales_others_subtotal,
            'salesinvoiceitem' => $salesinvoiceitem,
            'expenditure' => $expenditure,
            'expenditure_total' => $expenditure_total,
            'capital_money_total' => $capital_money_today,
            'discount_total' => $discount_total,
            'ppn_total' => $ppn_total,
            'sales_subtotal' => $sales_subtotal,
            'sales_total' => $sales_total - $ppn_total,
            'sales_cash_total' => $sales_cash_total,
            'sales_non_cash_total' => $sales_non_cash_total,
        ], 201);

    }

    public function getLoginState(Request $request)
    {
        return response([
            'state' => "login",
        ], 201);
    }

    public function getSalesPrintData(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'sales_invoice_id' => 'required',
        ]);

        $salesinvoice = SalesInvoice::select('sales_invoice.*', 'system_user.name')
            ->join('system_user', 'system_user.user_id', 'sales_invoice.created_id')
            ->where('sales_invoice.sales_invoice_id', $fields['sales_invoice_id'])
            ->first();

        $salesinvoiceitem = SalesInvoiceItem::select('sales_invoice_item.*', 'invt_item.item_name')
            ->join('invt_item', 'invt_item.item_id', 'sales_invoice_item.item_id')
            ->where('sales_invoice_item.sales_invoice_id', $fields['sales_invoice_id'])
            ->get();

        $preferencecompany = User::select('preference_company.*')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        return response([
            'salesinvoice' => $salesinvoice,
            'salesinvoiceitem' => $salesinvoiceitem,
            'preferencecompany' => $preferencecompany
        ], 201);
    }

    public function getExpenditurePrintData(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'expenditure_id' => 'required',
        ]);

        $expenditure = Expenditure::select('expenditure.*', 'system_user.name')
            ->join('system_user', 'system_user.user_id', 'expenditure.created_id')
            ->where('expenditure.expenditure_id', $fields['expenditure_id'])
            ->first();

        $preferencecompany = User::select('preference_company.*')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        return response([
            'expenditure' => $expenditure,
            'preferencecompany' => $preferencecompany
        ], 201);
    }

    public function getDashboardPrintData(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'dashboard_start_date' => 'required',
            'dashboard_end_date' => 'required',
        ]);

        $preferencecompany = User::select('preference_company.*')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $capitalmoney = CapitalMoney::select('*')
            ->where('capital_money_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('capital_money_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $preferencecompany['company_id'])
            ->get();

        $capitalmoneytoday = CapitalMoney::select('*')
            ->where('capital_money_date', date("Y-m-d"))
            ->where('company_id', $preferencecompany['company_id'])
            ->first();

        $salesinvoicecash = SalesInvoice::select('*')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $preferencecompany['company_id'])
            ->where('data_state', 0)
            ->where('payment_method', 1)
            ->get();

        $salesinvoicegopay = SalesInvoice::select('*')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $preferencecompany['company_id'])
            ->where('data_state', 0)
            ->where('payment_method', 3)
            ->get();

        $salesinvoiceovo = SalesInvoice::select('*')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $preferencecompany['company_id'])
            ->where('data_state', 0)
            ->where('payment_method', 4)
            ->get();

        $salesinvoiceshopeepay = SalesInvoice::select('*')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $preferencecompany['company_id'])
            ->where('data_state', 0)
            ->where('payment_method', 5)
            ->get();

        $salesinvoiceothers = SalesInvoice::select('*')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $preferencecompany['company_id'])
            ->where('data_state', 0)
            ->where('payment_method', 6)
            ->get();

        $salesinvoiceitem = SalesInvoiceItem::select('invt_item.item_name', DB::raw('SUM(sales_invoice_item.quantity) as `quantity`'), DB::raw('SUM(sales_invoice_item.subtotal_amount) as `subtotal_amount`'))
            ->join('invt_item', 'invt_item.item_id', 'sales_invoice_item.item_id')
            ->join('sales_invoice', 'sales_invoice.sales_invoice_id', 'sales_invoice_item.sales_invoice_id')
            ->where('sales_invoice.company_id', $preferencecompany['company_id'])
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('sales_invoice.paid_amount', '!=', 0)
            ->where('sales_invoice.data_state', 0)
            ->groupBy('sales_invoice_item.quantity', 'sales_invoice_item.subtotal_amount', 'invt_item.item_name')
            ->get();

        $expenditure = Expenditure::select('*')
            ->where('expenditure_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('expenditure_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $preferencecompany['company_id'])
            ->where('data_state', 0)
            ->get();

        $sales_total = 0;
        $sales_cash_subtotal = 0;
        $sales_gopay_subtotal = 0;
        $sales_ovo_subtotal = 0;
        $sales_shopeepay_subtotal = 0;
        $sales_others_subtotal = 0;
        $discount_total = 0;
        $ppn_total = 0;
        $sales_subtotal = 0;
        $sales_cash_total = 0;
        $sales_non_cash_total = 0;
        $expenditure_total = 0;
        $capital_money_total = 0;
        $capital_money_today = 0;

        foreach ($capitalmoney as $key => $val) {
            $capital_money_total += $val['capital_money_amount'];
        }

        foreach ($salesinvoicecash as $key => $val) {
            $discount_total += $val['discount_amount_total'];
            $ppn_total += $val['ppn_amount_total'];
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_cash_subtotal += $val['subtotal_amount'];
            $sales_cash_total += $val['total_amount'];
        }
        foreach ($salesinvoicegopay as $key => $val) {
            $discount_total += $val['discount_amount_total'];
            $ppn_total += $val['ppn_amount_total'];
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_gopay_subtotal += $val['subtotal_amount'];
            $sales_non_cash_total += $val['total_amount'];
        }
        foreach ($salesinvoiceshopeepay as $key => $val) {
            $discount_total += $val['discount_amount_total'];
            $ppn_total += $val['ppn_amount_total'];
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_shopeepay_subtotal += $val['subtotal_amount'];
            $sales_non_cash_total += $val['total_amount'];
        }
        foreach ($salesinvoiceovo as $key => $val) {
            $discount_total += $val['discount_amount_total'];
            $ppn_total += $val['ppn_amount_total'];
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_ovo_subtotal += $val['subtotal_amount'];
            $sales_non_cash_total += $val['total_amount'];
        }
        foreach ($salesinvoiceothers as $key => $val) {
            $discount_total += $val['discount_amount_total'];
            $ppn_total += $val['ppn_amount_total'];
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_others_subtotal += $val['subtotal_amount'];
            $sales_non_cash_total += $val['total_amount'];
        }

        foreach ($expenditure as $key => $val) {
            $expenditure_total += $val['expenditure_amount'];
        }

        if ($capitalmoneytoday) {
            $capital_money_today = $capitalmoneytoday['capital_money_amount'];
        }

        return response([
            'sales_cash_subtotal' => $sales_cash_subtotal,
            'sales_gopay_subtotal' => $sales_gopay_subtotal,
            'sales_ovo_subtotal' => $sales_ovo_subtotal,
            'sales_shopeepay_subtotal' => $sales_shopeepay_subtotal,
            'sales_others_subtotal' => $sales_others_subtotal,
            'expenditure_total' => $expenditure_total,
            'capital_money_total' => $capital_money_today,
            'discount_total' => $discount_total,
            'ppn_total' => $ppn_total,
            'sales_subtotal' => $sales_subtotal,
            'sales_total' => $sales_total - $ppn_total,
            'sales_cash_total' => $sales_cash_total,
            'sales_non_cash_total' => $sales_non_cash_total,
            'preferencecompany' => $preferencecompany
        ], 201);
    }

    public function registerGuest()
    {
        $lastguest = PreferenceCompany::select('company_name')
            ->where('guest_state', 1)
            ->orderBy('created_at', 'DESC')
            ->first();

        $no = substr($lastguest['company_name'], 8);

        $company = PreferenceCompany::create([
            'company_name' => 'company_' . ($no + 1),
            'company_address' => '-',
            'company_phone_number' => '-',
            'company_mobile_number' => '-',
            'company_email' => '-',
            'company_website' => '-',
            'guest_state' => 1,
        ]);

        $newcompany = PreferenceCompany::select('company_id')
            ->where('company_name', 'company_' . ($no + 1))
            ->first();

        $user = User::create([
            'company_id' => $newcompany['company_id'],
            'user_group_id' => 2,
            'full_name' => 'Guest ' . ($no + 1),
            'name' => 'guest_' . ($no + 1),
            'password' => Hash::make("123456"),
        ]);

        $user = User::select('system_user.*', 'system_user_group.user_group_name', 'preference_company.guest_state')
            ->join('system_user_group', 'system_user_group.user_group_id', 'system_user.user_group_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('name', 'guest_' . ($no + 1))
            ->first();

        $token = $user->createToken('token-name')->plainTextToken;

        $response = [
            'data' => $user,
            'token' => $token
        ];

        return response($response, 201);
    }

    public function getPreferenceCompany(Request $request)
    {
        $user = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $request->user_id)
            ->first();

        $preferencecompany = PreferenceCompany::where('company_id', $user['company_id'])
            ->get();

        $response = [
            'data' => $preferencecompany
        ];

        return response($response, 201);
    }

    //!KASIH IBU

    public function insertSalesInvoiceNominal(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'item_id' => 'required',
            'subtotal_amount' => 'required',
            'subtotal_item' => 'required',
            'discount_amount_total' => 'required',
            'discount_amount_total' => 'required',
            'total_amount' => 'required',
            'paid_amount' => 'required',
            'index_button' => 'required',
            'item_cost' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $disc_percentage = 0;
        if ($request->discount_percentage_total > 0 && $request->discount_percentage_total != '' && $request->discount_percentage_total != null) {
            $disc_percentage = $request->discount_percentage_total;
        }

        if ($fields['index_button'] <= 8) {
            $payment_method = 0;
        } else if ($fields['index_button'] == 9) {
            $payment_method = 1;
        } else if ($fields['index_button'] == 10) {
            $payment_method = 2;
        } else if ($fields['index_button'] == 11) {
            $payment_method = 3;
        } else if ($fields['index_button'] == 12) {
            $payment_method = 4;
        }

        $data = array(
            'sales_invoice_date' => date("Y-m-d"),
            'subtotal_amount' => $fields['subtotal_amount'],
            'subtotal_item' => $fields['subtotal_item'],
            'discount_percentage_total' => $disc_percentage,
            'discount_amount_total' => $fields['discount_amount_total'],
            'total_amount' => $fields['total_amount'],
            'ppn_amount_total' => $fields['ppn_amount_total'],
            'paid_amount' => $fields['paid_amount'],
            'change_amount' => $fields['paid_amount'] - $fields['total_amount'],
            'table_no' => $request->table_no,
            'created_id' => $fields['user_id'],
            'payment_method' => $payment_method,
            'company_id' => $company_id['company_id']
        );

        if (SalesInvoice::create($data)) {
            $salesinvoicelast = SalesInvoice::where('created_id', $fields['user_id'])->orderBy('sales_invoice_id', 'DESC')->first();
            $item = InvtItem::where('item_id', $fields['item_id'])->first();
            $data_item = array(
                'sales_invoice_id' => $salesinvoicelast['sales_invoice_id'],
                'item_category_id' => $item['item_category_id'],
                'item_unit_id' => $item['item_unit_id'],
                'item_id' => $fields['item_id'],
                'quantity' => 1,
                'item_unit_price' => $data['subtotal_amount'],
                'item_unit_cost' => $fields['item_cost'],
                'subtotal_amount' => $data['subtotal_amount'],
                'subtotal_amount_after_discount' => $data['total_amount'],
                'discount_percentage' => 0,
                'discount_amount' => 0,
                'created_id' => $fields['user_id'],
                'company_id' => $company_id['company_id']
            );

            if (SalesInvoiceItem::create($data_item)) {
                $itemstock = InvtItemStock::where('item_id', $data_item['item_id'])->first();
                if ($itemstock) {
                    $itemstock->last_balance = $itemstock['last_balance'] - $data_item['quantity'];
                    $itemstock->save();
                } else {
                    $warehouse = InvtWarehouse::select('warehouse_id')
                        ->where('company_id', $company_id['company_id'])
                        ->first();

                    $data_stock = array(
                        'company_id' => $company_id['company_id'],
                        'warehouse_id' => $warehouse['warehouse_id'],
                        'item_id' => $fields['item_id'],
                        'item_unit_id' => $item['item_unit_id'],
                        'item_category_id' => $item['item_category_id'],
                        'last_balance' => ($data_item['quantity']) * -1,
                        'created_id' => $fields['user_id'],
                    );
                    InvtItemStock::create($data_stock);
                }
            } else {
                return response([
                    'message' => 'Data Tidak Berhasil Disimpan'
                ], 401);
            }

            $transaction_module_code = 'PJL';
            $transaction_module_id = $this->getTransactionModuleID($transaction_module_code);

            $journal = array(
                'company_id' => $company_id['company_id'],
                'journal_voucher_status' => 1,
                'journal_voucher_description' => $this->getTransactionModuleName($transaction_module_code),
                'journal_voucher_title' => $this->getTransactionModuleName($transaction_module_code),
                'transaction_module_id' => $transaction_module_id,
                'transaction_module_code' => $transaction_module_code,
                'journal_voucher_date' => $data['sales_invoice_date'],
                'journal_voucher_period' => date('Ym'),
                'updated_id' => $fields['user_id'],
                'created_id' => $fields['user_id']
            );

            if (JournalVoucher::create($journal)) {
                $journal_voucher_id = JournalVoucher::where('company_id', $company_id['company_id'])
                    ->orderBy('created_at', 'DESC')
                    ->first();

                $account_setting_name = 'sales_cash_account';
                $account_id = $this->getAccountId($account_setting_name, $company_id['company_id']);
                $account_status = $this->getAccountStatus($account_setting_name, $company_id['company_id']);
                $account_default_status = $this->getAccountDefaultStatus($account_id);
                if ($account_status == 0) {
                    $debit_ammount = $fields['total_amount'];
                    $credit_ammount = 0;
                } else {
                    $credit_ammount = $fields['total_amount'];
                    $debit_ammount = 0;
                }
                $journal_debit = array(
                    'company_id' => $company_id['company_id'],
                    'journal_voucher_id' => $journal_voucher_id['journal_voucher_id'],
                    'account_id' => $account_id,
                    'journal_voucher_amount' => $fields['total_amount'],
                    'account_id_default_status' => $account_default_status,
                    'account_id_status' => $account_status,
                    'journal_voucher_debit_amount' => $debit_ammount,
                    'journal_voucher_credit_amount' => $credit_ammount,
                    'updated_id' => $fields['user_id'],
                    'created_id' => $fields['user_id']
                );

                JournalVoucherItem::create($journal_debit);
                $account_setting_name = 'sales_account';
                $account_id = $this->getAccountId($account_setting_name, $company_id['company_id']);
                $account_status = $this->getAccountStatus($account_setting_name, $company_id['company_id']);
                $account_default_status = $this->getAccountDefaultStatus($account_id);
                if ($account_status == 0) {
                    $debit_ammount = $fields['total_amount'];
                    $credit_ammount = 0;
                } else {
                    $credit_ammount = $fields['total_amount'];
                    $debit_ammount = 0;
                }
                $journal_credit = array(
                    'company_id' => $company_id['company_id'],
                    'journal_voucher_id' => $journal_voucher_id['journal_voucher_id'],
                    'account_id' => $account_id,
                    'journal_voucher_amount' => $fields['total_amount'],
                    'account_id_default_status' => $account_default_status,
                    'account_id_status' => $account_status,
                    'journal_voucher_debit_amount' => $debit_ammount,
                    'journal_voucher_credit_amount' => $credit_ammount,
                    'updated_id' => $fields['user_id'],
                    'created_id' => $fields['user_id']
                );
                JournalVoucherItem::create($journal_credit);

                // $account_setting_name = 'sales_ppn_account';
                // $account_id = $this->getAccountId($account_setting_name, $company_id['company_id']);
                // $account_status = $this->getAccountStatus($account_setting_name, $company_id['company_id']);
                // $account_default_status = $this->getAccountDefaultStatus($account_id);
                // if($account_status == 0){
                //     $debit_ammount = $fields['ppn_amount_total'];
                //     $credit_ammount = 0;
                // }else{
                //     $credit_ammount = $fields['ppn_amount_total'];
                //     $debit_ammount = 0;
                // }
                // $journal_credit = array(
                //     'company_id'                    => $company_id['company_id'],
                //     'journal_voucher_id'            => $journal_voucher_id['journal_voucher_id'],
                //     'account_id'                    => $account_id,
                //     'journal_voucher_amount'        => $fields['ppn_amount_total'],
                //     'account_id_default_status'     => $account_default_status,
                //     'account_id_status'             => $account_status,
                //     'journal_voucher_debit_amount'  => $debit_ammount,
                //     'journal_voucher_credit_amount' => $credit_ammount,
                //     'updated_id'                    => $fields['user_id'],
                //     'created_id'                    => $fields['user_id']
                // );

                // JournalVoucherItem::create($journal_credit);

                $account_setting_name = 'purchase_cash_account';
                $account_id = $this->getAccountId($account_setting_name, $company_id['company_id']);
                $account_status = $this->getAccountStatus($account_setting_name, $company_id['company_id']);
                $account_default_status = $this->getAccountDefaultStatus($account_id);
                if ($account_status == 0) {
                    $debit_ammount = $fields['item_cost'];
                    $credit_ammount = 0;
                } else {
                    $credit_ammount = $fields['item_cost'];
                    $debit_ammount = 0;
                }
                $journal_debit = array(
                    'company_id' => $company_id['company_id'],
                    'journal_voucher_id' => $journal_voucher_id['journal_voucher_id'],
                    'account_id' => $account_id,
                    'journal_voucher_amount' => $fields['item_cost'],
                    'account_id_default_status' => $account_default_status,
                    'account_id_status' => $account_status,
                    'journal_voucher_debit_amount' => $debit_ammount,
                    'journal_voucher_credit_amount' => $credit_ammount,
                    'updated_id' => $fields['user_id'],
                    'created_id' => $fields['user_id']
                );
                JournalVoucherItem::create($journal_debit);

                $account_setting_name = 'purchase_account';
                $account_id = $this->getAccountId($account_setting_name, $company_id['company_id']);
                $account_status = $this->getAccountStatus($account_setting_name, $company_id['company_id']);
                $account_default_status = $this->getAccountDefaultStatus($account_id);
                if ($account_status == 0) {
                    $debit_ammount = $fields['item_cost'];
                    $credit_ammount = 0;
                } else {
                    $credit_ammount = $fields['item_cost'];
                    $debit_ammount = 0;
                }
                $journal_credit = array(
                    'company_id' => $company_id['company_id'],
                    'journal_voucher_id' => $journal_voucher_id['journal_voucher_id'],
                    'account_id' => $account_id,
                    'journal_voucher_amount' => $fields['item_cost'],
                    'account_id_default_status' => $account_default_status,
                    'account_id_status' => $account_status,
                    'journal_voucher_debit_amount' => $debit_ammount,
                    'journal_voucher_credit_amount' => $credit_ammount,
                    'updated_id' => $fields['user_id'],
                    'created_id' => $fields['user_id']
                );
                JournalVoucherItem::create($journal_credit);
            }

        } else {
            return response([
                'message' => 'Data Tidak Berhasil Disimpan'
            ], 401);
        }


        return response([
            'message' => 'Data Berhasil Disimpan'
        ], 201);

    }

    public function getExpenditureAccount(Request $request)
    {
        $expenditureaccount = AcctAccount::select('account_id', 'account_code', 'account_name')
            ->where('account_id', 36)
            ->orWhere('account_id', 38)
            ->orWhere('account_id', 49)
            ->orWhere('account_id', 43)
            // ->orWhere('account_id', 34)
            ->get();

        return response([
            'expenditureaccount' => $expenditureaccount,
        ], 201);
    }

    //!DASHBOARD RECAP

    public function getDashboardGeprek(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'dashboard_start_date' => 'required',
            'dashboard_end_date' => 'required',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $capitalmoney = CapitalMoney::select('*')
            ->where('capital_money_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('capital_money_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->get();

        $capitalmoneytoday = CapitalMoney::select('*')
            ->where('capital_money_date', date("Y-m-d"))
            ->where('company_id', $company_id['company_id'])
            ->first();

        $purchaseinvoice = PurchaseInvoice::select('total_amount')
            ->where('purchase_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('purchase_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->get();

        $salesinvoicecash = SalesInvoice::select('*')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->where('payment_method', 0)
            ->get();

        $salesinvoicegopay = SalesInvoice::select('*')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->where('payment_method', 1)
            ->get();

        $salesinvoiceovo = SalesInvoice::select('*')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->where('payment_method', 2)
            ->get();

        $salesinvoiceshopeepay = SalesInvoice::select('*')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->where('payment_method', 3)
            ->get();

        $salesinvoiceothers = SalesInvoice::select('*')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->where('payment_method', 4)
            ->get();

        $salesinvoiceitem = SalesInvoiceItem::select('invt_item.item_name', DB::raw('SUM(sales_invoice_item.quantity) as `quantity`'), DB::raw('SUM(sales_invoice_item.subtotal_amount) as `subtotal_amount`'))
            ->join('invt_item', 'invt_item.item_id', 'sales_invoice_item.item_id')
            ->join('sales_invoice', 'sales_invoice.sales_invoice_id', 'sales_invoice_item.sales_invoice_id')
            ->where('sales_invoice.company_id', $company_id['company_id'])
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('sales_invoice.paid_amount', '!=', 0)
            ->where('sales_invoice.data_state', 0)
            ->groupBy('invt_item.item_name')
            ->get();

        $expenditure = Expenditure::select('*')
            ->where('expenditure_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('expenditure_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->get();

        $expenditurejurnal = JournalVoucherItem::select('acct_journal_voucher_item.journal_voucher_amount')
            ->join('acct_journal_voucher', 'acct_journal_voucher.journal_voucher_id', 'acct_journal_voucher_item.journal_voucher_id')
            ->where('acct_journal_voucher.journal_voucher_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('acct_journal_voucher.journal_voucher_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('acct_journal_voucher.journal_voucher_status', 0)
            ->where('acct_journal_voucher_item.account_id', 4)
            ->where('acct_journal_voucher_item.account_id_status', 1)
            ->where('acct_journal_voucher_item.data_state', 0)
            ->get();

        $sales_total = 0;
        $sales_cash_subtotal = 0;
        $sales_gopay_subtotal = 0;
        $sales_ovo_subtotal = 0;
        $sales_shopeepay_subtotal = 0;
        $sales_others_subtotal = 0;
        $discount_total = 0;
        $ppn_total = 0;
        $sales_subtotal = 0;
        $sales_cash_total = 0;
        $sales_non_cash_total = 0;
        $expenditure_total = 0;
        $capital_money_total = 0;
        $capital_money_today = 0;
        $purchase_total = 0;

        foreach ($capitalmoney as $key => $val) {
            $capital_money_total += $val['capital_money_amount'];
        }

        foreach ($salesinvoicecash as $key => $val) {
            $discount_total += $val['discount_amount_total'];
            $ppn_total += $val['ppn_amount_total'];
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_cash_subtotal += $val['subtotal_amount'];
            $sales_cash_total += $val['total_amount'];
        }
        foreach ($salesinvoicegopay as $key => $val) {
            $discount_total += $val['discount_amount_total'];
            $ppn_total += $val['ppn_amount_total'];
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_gopay_subtotal += $val['subtotal_amount'];
            $sales_non_cash_total += $val['total_amount'];
        }
        foreach ($salesinvoiceshopeepay as $key => $val) {
            $discount_total += $val['discount_amount_total'];
            $ppn_total += $val['ppn_amount_total'];
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_shopeepay_subtotal += $val['subtotal_amount'];
            $sales_non_cash_total += $val['total_amount'];
        }
        foreach ($salesinvoiceovo as $key => $val) {
            $discount_total += $val['discount_amount_total'];
            $ppn_total += $val['ppn_amount_total'];
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_ovo_subtotal += $val['subtotal_amount'];
            $sales_non_cash_total += $val['total_amount'];
        }
        foreach ($salesinvoiceothers as $key => $val) {
            $discount_total += $val['discount_amount_total'];
            $ppn_total += $val['ppn_amount_total'];
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_others_subtotal += $val['subtotal_amount'];
            $sales_non_cash_total += $val['total_amount'];
        }

        foreach ($purchaseinvoice as $key => $val) {
            $purchase_total += $val['total_amount'];
        }

        foreach ($expenditure as $key => $val) {
            $expenditure_total += $val['expenditure_amount'];
        }

        foreach ($expenditurejurnal as $key => $val) {
            $expenditure_total += $val['journal_voucher_amount'];
        }

        if ($capitalmoneytoday) {
            $capital_money_today = $capitalmoneytoday['capital_money_amount'];
        }

        return response([
            'sales_cash_subtotal' => $sales_cash_subtotal,
            'sales_gopay_subtotal' => $sales_gopay_subtotal,
            'sales_ovo_subtotal' => $sales_ovo_subtotal,
            'sales_shopeepay_subtotal' => $sales_shopeepay_subtotal,
            'sales_others_subtotal' => $sales_others_subtotal,
            'salesinvoiceitem' => $salesinvoiceitem,
            'expenditure' => $expenditure,
            'expenditure_total' => $expenditure_total,
            'capital_money_total' => $capital_money_today,
            'discount_total' => $discount_total,
            'ppn_total' => $ppn_total,
            'sales_subtotal' => $sales_subtotal,
            'sales_total' => $sales_total - $ppn_total,
            'sales_cash_total' => $sales_cash_total,
            'sales_non_cash_total' => $sales_non_cash_total,
            'purchase_total' => $purchase_total,
        ], 201);
    }

    public function getDashboardSarmed(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'dashboard_start_date' => 'required',
            'dashboard_end_date' => 'required',
        ]);

        $company_id = 1;

        $purchaseinvoice = PurchaseInvoiceSarmed::select('total_amount')
            ->where('purchase_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('purchase_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id)
            ->where('data_state', 0)
            ->get();

        $purchaseinvoiceitem = PurchaseInvoiceItemSarmed::select('invt_item.item_name', DB::raw('SUM(purchase_invoice_item.quantity) as `quantity`'), DB::raw('SUM(purchase_invoice_item.subtotal_amount) as `subtotal_amount`'))
            ->join('purchase_invoice', 'purchase_invoice.purchase_invoice_id', 'purchase_invoice_item.purchase_invoice_id')
            ->join('invt_item', 'invt_item.item_id', 'purchase_invoice_item.item_id')
            ->where('purchase_invoice.purchase_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('purchase_invoice.purchase_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('purchase_invoice.company_id', $company_id)
            ->where('purchase_invoice.data_state', 0)
            ->groupBy('invt_item.item_name')
            ->get();

        $salesinvoice = SalesInvoiceSarmed::select('subtotal_amount', 'discount_amount_total', 'ppn_amount_total', 'total_amount')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id)
            ->where('data_state', 0)
            ->get();

        $salesinvoiceitem = SalesInvoiceItemSarmed::select('invt_item.item_name', DB::raw('SUM(sales_invoice_item.quantity) as `quantity`'), DB::raw('SUM(sales_invoice_item.subtotal_amount) as `subtotal_amount`'))
            ->join('sales_invoice', 'sales_invoice.sales_invoice_id', 'sales_invoice_item.sales_invoice_id')
            ->join('invt_item', 'invt_item.item_id', 'sales_invoice_item.item_id')
            ->where('sales_invoice.sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice.sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('sales_invoice.company_id', $company_id)
            ->where('sales_invoice.data_state', 0)
            ->groupBy('invt_item.item_name')
            ->get();

        $salesconsignment = SalesConsignmentSarmed::select('subtotal_amount', 'discount_amount_total', 'ppn_amount_total', 'total_amount')
            ->where('consignment_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('consignment_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', $company_id)
            ->where('consignment_type', 1)
            ->where('data_state', 0)
            ->get();

        $salesconsignmentitem = SalesConsignmentItemSarmed::select('invt_item.item_name', DB::raw('SUM(sales_consignment_item.quantity) as `quantity`'), DB::raw('SUM(sales_consignment_item.subtotal_amount) as `subtotal_amount`'))
            ->join('sales_consignment', 'sales_consignment.consignment_id', 'sales_consignment_item.consignment_id')
            ->join('invt_item', 'invt_item.item_id', 'sales_consignment_item.item_id')
            ->where('sales_consignment.consignment_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_consignment.consignment_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('sales_consignment.company_id', $company_id)
            ->where('sales_consignment.consignment_type', 1)
            ->where('sales_consignment.data_state', 0)
            ->groupBy('invt_item.item_name')
            ->get();

        $purchase_total = 0;
        $sales_total = 0;
        $sales_subtotal = 0;
        $sales_konsi_subtotal = 0;
        $sales_konsi_total = 0;
        $sales_diskon_total = 0;
        $sales_ppn_total = 0;

        foreach ($purchaseinvoice as $key => $val) {
            $purchase_total += $val['total_amount'];
        }

        foreach ($salesinvoice as $key => $val) {
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_diskon_total += $val['discount_amount_total'];
            $sales_ppn_total += $val['ppn_amount_total'];
        }

        foreach ($salesconsignment as $key => $val) {
            $sales_konsi_subtotal += $val['subtotal_amount'];
            $sales_konsi_total += $val['total_amount'];
            $sales_diskon_total += $val['discount_amount_total'];
            $sales_ppn_total += $val['ppn_amount_total'];
        }

        return response([
            'purchase_total' => $purchase_total,
            'sales_subtotal' => $sales_subtotal,
            'sales_konsi_subtotal' => $sales_konsi_subtotal,
            'sales_total' => $sales_total - $sales_ppn_total,
            'sales_diskon_total' => $sales_diskon_total,
            'sales_ppn_total' => $sales_ppn_total,
            'purchaseinvoiceitem' => $purchaseinvoiceitem,
            'salesinvoiceitem' => $salesinvoiceitem,
            'salesconsignmentitem' => $salesconsignmentitem,
        ], 201);
    }

    public function getDashboardRekap(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required',
            'dashboard_start_date' => 'required',
            'dashboard_end_date' => 'required',
        ]);

        //company_id 1 sarmed, 2 geprek

        $pisarmed = PurchaseInvoiceSarmed::select('total_amount')
            ->where('purchase_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('purchase_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', 1)
            ->where('data_state', 0)
            ->get();

        $pigeprek = PurchaseInvoice::select('total_amount')
            ->where('purchase_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('purchase_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', 2)
            ->where('data_state', 0)
            ->get();

        $sisarmed = SalesInvoiceSarmed::select('subtotal_amount', 'discount_amount_total', 'ppn_amount_total', 'total_amount')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', 1)
            ->where('data_state', 0)
            ->get();

        $sigeprek = SalesInvoice::select('subtotal_amount', 'discount_amount_total', 'ppn_amount_total', 'total_amount')
            ->where('sales_invoice_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('sales_invoice_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', 2)
            ->where('data_state', 0)
            ->get();

        $salesconsignment = SalesConsignmentSarmed::select('subtotal_amount', 'discount_amount_total', 'ppn_amount_total', 'total_amount')
            ->where('consignment_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('consignment_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', 1)
            ->where('consignment_type', 1)
            ->where('data_state', 0)
            ->get();

        $expenditure = Expenditure::select('*')
            ->where('expenditure_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('expenditure_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('company_id', 2)
            ->where('data_state', 0)
            ->get();

        $expenditurejurnal = JournalVoucherItem::select('acct_journal_voucher_item.journal_voucher_amount')
            ->join('acct_journal_voucher', 'acct_journal_voucher.journal_voucher_id', 'acct_journal_voucher_item.journal_voucher_id')
            ->where('acct_journal_voucher.journal_voucher_date', '>=', substr($fields['dashboard_start_date'], 0, 10))
            ->where('acct_journal_voucher.journal_voucher_date', '<=', substr($fields['dashboard_end_date'], 0, 10))
            ->where('acct_journal_voucher.journal_voucher_status', 0)
            ->where('acct_journal_voucher_item.account_id', 4)
            ->where('acct_journal_voucher_item.account_id_status', 1)
            ->where('acct_journal_voucher_item.data_state', 0)
            ->get();

        $purchase_geprek = 0;
        $purchase_sarmed = 0;
        $purchase_total = 0;
        $sales_geprek = 0;
        $sales_sarmed = 0;
        $sales_subtotal = 0;
        $sales_total = 0;
        $sales_diskon_total = 0;
        $sales_ppn_total = 0;
        $expenditure_total = 0;

        foreach ($pisarmed as $key => $val) {
            $purchase_total += $val['total_amount'];
        }

        foreach ($pigeprek as $key => $val) {
            $purchase_total += $val['total_amount'];
        }

        foreach ($sisarmed as $key => $val) {
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_diskon_total += $val['discount_amount_total'];
            $sales_ppn_total += $val['ppn_amount_total'];
        }

        foreach ($sigeprek as $key => $val) {
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_diskon_total += $val['discount_amount_total'];
            $sales_ppn_total += $val['ppn_amount_total'];
        }

        foreach ($salesconsignment as $key => $val) {
            $sales_subtotal += $val['subtotal_amount'];
            $sales_total += $val['total_amount'];
            $sales_diskon_total += $val['discount_amount_total'];
            $sales_ppn_total += $val['ppn_amount_total'];
        }

        foreach ($expenditure as $key => $val) {
            $expenditure_total += $val['expenditure_amount'];
        }

        foreach ($expenditurejurnal as $key => $val) {
            $expenditure_total += $val['journal_voucher_amount'];
        }

        return response([
            'purchase_total' => $purchase_total,
            'sales_subtotal' => $sales_subtotal,
            'sales_total' => $sales_total - $sales_ppn_total,
            'sales_diskon_total' => $sales_diskon_total,
            'sales_ppn_total' => $sales_ppn_total,
            'expenditure_total' => $expenditure_total,
        ], 201);
    }

    //!Tambahan


    public function printerKitchenAddress(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required|string',
        ]);

        // Check username
        $preferencecompany = User::select('preference_company.printer_kitchen_address')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        if ($preferencecompany) {
            return response([
                'data' => $preferencecompany['printer_kitchen_address'],
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Ditemukan'
            ], 401);
        }
    }

    public function updatePrinterKitchenAddress(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required|string',
            'printer_kitchen_address' => 'required|string',
        ]);

        // Check username
        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $preferencecompany = PreferenceCompany::findOrFail($company_id['company_id']);
        $preferencecompany->printer_kitchen_address = $fields['printer_kitchen_address'];

        if ($preferencecompany->save()) {
            return response([
                'message' => 'Ganti Nama Printer Dapur Berhasil'
            ], 201);
        } else {
            return response([
                'message' => 'Ganti Nama Printer Dapur Tidak Berhasil'
            ], 401);
        }
    }

    public function getInvtItemNewMenu(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required|string',
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $invtcategory = InvtItemCategory::select('*')
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->orderBy('item_category_name', 'ASC')
            ->get();

        foreach ($invtcategory as $key => $val) {
            $stock_total = InvtItemStock::where('item_category_id', $val['item_category_id'])
                ->where('data_state', 0)
                ->sum('last_balance');

            if (!$stock_total) {
                $stock_total = 0;
            }

            $invtcategory[$key]['stock_total'] = $stock_total;
        }

        if ($invtcategory) {
            return response([
                'data' => $invtcategory
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Ditemukan'
            ], 401);
        }
    }

    public function getInvtAllItem(Request $request)
    {
        $fields = $request->validate([
            'user_id' => 'required'
        ]);

        $company_id = User::select('preference_company.company_id')
            ->join('preference_company', 'preference_company.company_id', 'system_user.company_id')
            ->where('system_user.user_id', $fields['user_id'])
            ->first();

        $invitem = InvtItem::select('*')
            ->where('company_id', $company_id['company_id'])
            ->where('data_state', 0)
            ->where('item_category_id', 24)
            ->orWhere('item_category_id', 25)
            ->orderBy('item_name', 'ASC')
            ->get();

        foreach ($invitem as $key => $val) {
            $stock_total = InvtItemStock::where('item_id', $val['item_id'])
                ->where('data_state', 0)
                ->sum('last_balance');

            if (!$stock_total) {
                $stock_total = 0;
            }

            $invitem[$key]['stock_total'] = $stock_total;
        }

        if ($invitem) {
            return response([
                'data' => $invitem
            ], 201);
        } else {
            return response([
                'message' => 'Data Tidak Ditemukan'
            ], 401);
        }
    }
}
