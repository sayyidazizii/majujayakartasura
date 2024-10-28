@extends('layouts.user_type.auth')

@section('content')
    <div>
        <div class="alert"></div>
        <div class="row">
            <div class="card mb-4 mx-4">
                <div class="card-header pb-1">
                    <div class="d-flex flex-row justify-content-between">
                        <h5>Tambah Item</h5>
                        <a href="{{ route('item.index') }}" class="btn btn-sm bg-gradient-primary" type='button'>
                            Kembali</a>
                    </div>
                </div>

                <form action="{{ route('item.update', $item->id) }}" method="post">
                    @csrf
                    @method('put')
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-6">
                                <div class="form-group">
                                    <div class="text-dark">Nama Item</div>
                                    <input required placeholder="Masukan Item" type="text" class='form-control'
                                        name="item_name" id="item_name" value="{{ $item->item_name }}">
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-6">
                                <div class="form-group">
                                    <div class="text-dark">Kode Item</div>
                                    <input required placeholder="Masukan Kode Item" type="text" class='form-control'
                                        name="item_code" id="item_code" value="{{ $item->item_code }}">
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-6">
                                <div class="form-group">
                                    <div class="text-dark">Barcode Item</div>
                                    <input required placeholder="Masukan Barcode Item" type="text" class='form-control'
                                        name="item_barcode" id="item_barcode" value="{{ $item->item_barcode }}">
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-6">
                                <div class="form-group">
                                    <div class="text-dark">Stock Item</div>
                                    <input required placeholder="Masukan Stock Item" type="number" class='form-control'
                                        name="last_balance" id="last_balance" value="{{ $item->stok->last_balance }}">
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-6">
                                <div class="form-group">
                                    <div class="text-dark">Harga Beli</div>
                                    <input required placeholder="Masukan Harga Beli Item" type="number"
                                        class='form-control' name="item_unit_cost" id="item_unit_cost"
                                        value="{{ $item->item_unit_cost }}">
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-6">
                                <div class="form-group">
                                    <div class="text-dark">Harga Jual</div>
                                    <input required placeholder="Masukan Harga Jual Item" type="number"
                                        class='form-control' name="item_unit_price" id="item_unit_price"
                                        value="{{ $item->item_unit_price }}">
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-6">
                                <div class="form-group">
                                    <div class="text-dark">Category</div>
                                    <select class="form-select" name="item_category_id" id="item_category_id">
                                        @foreach ($category as $key => $val)
                                            <option {{ $key == $item->item_category_id ? 'selected' : '' }}
                                                value="{{ $key }}">{{ $val }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-6">
                                <div class="form-group">
                                    <div class="text-dark">Unit</div>
                                    <select class="form-select" name="item_unit_id" id="item_unit_id">
                                        @foreach ($unit as $key => $val)
                                            <option {{ $key == $item->item_unit_id ? 'selected' : '' }}
                                                value="{{ $key }}">{{ $val }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="float-end">
                            <button class='btn btn-danger' type="reset">Batal</button>
                            <button class="btn btn-primary" type="submit">Simpan</button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
@endsection
