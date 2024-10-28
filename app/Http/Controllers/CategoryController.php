<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Category::get();
        // return view('content.Unit.index', ['data' => $data]);
        return view('content.Category.index', compact(var_name: 'data'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('content.Category.add');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            Category::create($request->all());
            DB::commit();
            return redirect()->route('category.index')
                ->with([
                    'msg' => 'Input Category Sukses',
                    'type' => 'success'
                ]);
        } catch (\Throwable $th) {
            DB::rollback();
            dd($th);
            report($th);
            return redirect()->route('category.index')
                ->with([
                    'msg' => 'Input Category Gagal',
                    'type' => 'danger'
                ]);

        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category)
    {
        return view(
            'content.Category.edit',
            compact('category')
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category)
    {
        try {
            DB::beginTransaction();
            $category->update($request->all());
            DB::commit();
            return redirect()->route('category.index')
                ->with(['msg' => 'Update kategori Sukses', 'type' => 'success']);

        } catch (\Throwable $th) {
            DB::rollback();
            dd($th);
            report($th);
            return redirect()->route('category.index')
                ->with(['msg' => 'Update kategori Gagal', 'type' => 'danger']);

        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        try {
            DB::beginTransaction();
            $category->delete();
            DB::commit();
            return redirect()->route('category.index')
                ->with(['msg' => 'Hapus Category Sukses', 'type' => 'success']);

        } catch (\Throwable $th) {
            DB::rollback();
            dd($th);
            report($th);
            return redirect()->route('category.index')
                ->with(['msg' => 'Hapus Category Gagal', 'type' => 'danger']);

        }
    }
}
