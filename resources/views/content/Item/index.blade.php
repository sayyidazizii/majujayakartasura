@extends('layouts.user_type.auth')

@section('content')
    <div>
        @if (session('msg'))
            <div class="alert alert-{{ session('type') }}" role="alert">
                {{ session('msg') }}
            </div>
        @endif
        <div class="row">
            <div class="card mb-4 mx-4">
                <div class="card-header pb-1">
                    <div class="d-flex flex-row justify-content-between">
                        <h5>Item</h5>
                        <a href="{{ route('item.create') }}" class="btn btn-sm bg-gradient-primary" type='button'>Tambah
                            Item</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Item</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $no = 1;
                                @endphp
                                @foreach ($data as $value)
                                    <tr>
                                        <td>{{ $no++ }}</td>
                                        <td>{{ $value->item_name }}</td>
                                        <td>
                                            <form action="{{ route('item.destroy', $value->id) }}" method="post">
                                                @method('delete')
                                                @csrf
                                                <button class="btn btn-sm bg-gradient-danger" type="submit">Hapus</button>
                                            </form>
                                            <a href="{{ route('item.edit', $value->id) }}" role="button"
                                                class="btn btn-sm bg-gradient-warning">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
