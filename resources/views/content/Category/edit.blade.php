@extends('layouts.user_type.auth')

@section('content')
    <div>
        <div class="alert"></div>
        <div class="row">
            <div class="card mb-4 mx-4">
                <div class="card-header pb-1">
                    <div class="d-flex flex-row justify-content-between">
                        <h5>Edit Category</h5>
                        <a href="{{ route('category.index') }}" class="btn btn-sm bg-gradient-primary" type='button'>
                            Kembali</a>
                    </div>
                </div>

                <form action="{{ route('category.update', $category->id) }}" method="post">
                    @csrf
                    @method('put')
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-12 col-md-6">
                                <div class="form-group">
                                    <div class="text-dark">Nama Category</div>
                                    <input required placeholder="Masukan Category" type="text" class='form-control'
                                        name="item_category_name" id="item_category_name"
                                        value="{{ $category->item_category_name }}">
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-6">
                                <div class="form-group">
                                    <div class="text-dark">Kode Category</div>
                                    <input required placeholder="Masukan Kode Category" type="text" class='form-control'
                                        name="item_category_code" id="item_category_code"
                                        value="{{ $category->item_category_code }}">
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
