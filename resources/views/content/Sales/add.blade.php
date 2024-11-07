@extends('layouts.user_type.auth')

@section('content')
    <script src="
                                                                                                                        https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js
                                                                                                                        ">
    </script>

    <script>
        function addItem() {
            let item_id = $('#item_id').val();
            let quantity = $('#quantity').val();
            let id = Math.floor(Math.random() * 100) + item_id;
            if (quantity == '' || quantity == '0') {
                alert('harap masukan quantity');
                return false;
            }
            $.ajax({
                type: "POST",
                url: "{{ route('sales-create-item') }}",
                data: {
                    'item_id': item_id,
                    '_token': '{{ csrf_token() }}'
                },
                success: function(data) {
                    let subTotal = (parseInt(data.item_unit_price) * parseInt(quantity));
                    let row = `
                    <tr class='sales-item' data-id='${id}' id='sales-item-${id}'>
                        <td>${data.item_name}</td>
                        <td>${data.item_unit_price}
                            <input type='hidden' name='sales_item[${id}][quantity]' value='${quantity}'>
                            <input type='hidden' name='sales_item[${id}][item_id]' value='${item_id}'>
                            <input type='hidden' name='sales_item[${id}][item_unit_price]' value='${data.item_unit_price}'>
                            <input type='hidden' name='sales_item[${id}][subTotal]' value='${subTotal}' id='subtotal-item-${id}'>
                        </td>
                        <td>${quantity}</td>
                        <td>${subTotal}</td>
                        <td><button class='btn btn-danger btn-sm' onclick='hapusItem(${id})'>hapus</button></td>
                    </tr>
                    `;
                    $('#sales-item-table').append(row);

                    calculate();
                }
            });
        }

        function hapusItem(id) {
            $(`#sales-item-${id}`).remove();
        }

        function calculate() {
            let total = 0;
            $('.sales-item').each(function() {
                let id = $(this).data('id');
                total += parseInt($('#subtotal-item-' + id).val());
            });
            $('#subtotal_amount').val(total);
            $('#total_amount').val(total);
        }
    </script>
    <form action="{{ route('sales.store') }}" method="post">
        @csrf
        <div class="card">
            <div class="row card-body">
                <div class="col-sm-12 col-md-6">
                    <div class="form-group">
                        <div class="text-dark">Item</div>
                        <select class="form-select" name="item_id" id="item_id">
                            @foreach ($item as $key => $val)
                                <option value="{{ $key }}">{{ $val }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-sm-12 col-md-6">
                    <div class="form-group">
                        <div class="text-dark">Quantity</div>
                        <input required placeholder="Masukan Quantity" type="number" class='form-control' name="quantity"
                            id="quantity">
                    </div>
                </div>
                <div class="col-sm-12 col-md-6">
                    <div class="form-group">
                        <div class="text-dark">Tanggal</div>
                        <input required placeholder="tanggal" type="date" class='form-control' name="sales_invoice_date"
                        id="sales_invoice_date">
                    </div>
                </div>
                <div class="card-footer">
                    <div class="float-end">
                        <button class="btn btn-success" type="button" onclick="addItem()">
                            Tambah
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nama Item</th>
                                <th>Harga</th>
                                <th>Quantity</th>
                                <th>SubTotal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id='sales-item-table'>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body row">
                <div class="col-sm-12 col-md-6">
                    <div class="form-group">
                        <div class="text-dark">Subtotal</div>
                        <input required readonly placeholder="" type="number" class='form-control' name="subtotal_amount"
                            id="subtotal_amount">
                    </div>
                </div>
                <div class="col-sm-12 col-md-6">
                    <div class="form-group">
                        <div class="text-dark">Bayar</div>
                        <input required placeholder="" type="number" class='form-control' name="paid_amount"
                            id="paid_amount">
                    </div>
                </div>
                <div class="col-sm-12 col-md-6">
                    <div class="form-group">
                        <div class="text-dark">Total</div>
                        <input required readonly placeholder="" type="number" class='form-control' name="total_amount"
                            id="total_amount">
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="float-end">
                    <button type="submit" class="btn btn-success">simpan</button>
                </div>
            </div>
        </div>
    </form>
@endsection
