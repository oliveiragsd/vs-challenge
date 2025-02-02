<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Product;
use Validator;
use Illuminate\Validation\ValidationException;
use App\Filters\ProductFilters;
use App\Rules\Sortable;
use Log;
use Auth;

class ProductsController extends Controller
{
    private $user;
    private $logging;

    /**
     * Create a new ProductsController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->user = Auth::user();
        $this->logging = config('logging.enabled');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, ProductFilters $filters)
    {
        $perPage = $request->perPage ?? 20;

        if (count($request->except('page')) > 0) {
            $validator = Validator::make($request->all(), [
                'name' => 'string|max:255',
                'brand' => 'string|max:255',
                'fromPrice' => 'numeric|min:0',
                'toPrice' => 'numeric|min:0',
                'fromStock' => 'integer|min:0',
                'toStock' => 'integer|min:0',
                'sort' => [new Sortable(['name', 'brand', 'price', 'stock'])],
                'perPage' => 'integer|min:1',
            ]);

            if ($validator->fails()) {
                throw ValidationException::withMessages($validator->errors()->all());
            }

            $products = Product::filter($filters)->paginate($perPage);
        } else {
            $products = Product::paginate($perPage);
        }

        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if ($this->logging) {
            Log::info('Requisição para criar produto. Usuário: ' . $this->user->id . '; Requisição: ' . json_encode($request->all()) . '.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->all());
        }

        $this->validateUniqueNameAndBrand($request);

        $product = Product::create($request->all());

        if ($this->logging) {
            Log::info('Produto criado. Usuário: ' . $this->user->id . '; Produto: ' . json_encode($product) . '.');
        }

        return response()->json([
            'message' => 'Product created.',
            'product' => $product
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $product
     * @return \Illuminate\Http\Response
     */
    public function show(Product $product)
    {
        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $product
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Product $product)
    {
        if ($this->logging) {
            Log::info('Requisição para atualizar produto. Usuário: ' . $this->user->id . '; Produto: ' . $product->id . '; Requisição: ' . json_encode($request->all()) . '.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'brand' => 'string|max:255',
            'price' => 'numeric|min:0',
            'stock' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->all());
        }

        $this->validateUniqueNameAndBrand($request, $product);

        $product->update($request->all());

        if ($this->logging) {
            Log::info('Produto atualizado. Usuário: ' . $this->user->id . '; Produto: ' . json_encode($product) . '.');
        }

        return response()->json([
            'message' => 'Product updated.',
            'product' => $product
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $product
     * @return \Illuminate\Http\Response
     */
    public function destroy(Product $product)
    {
        if ($this->logging) {
            Log::info('Requisição para remover produto. Usuário: ' . $this->user->id . '; Produto: ' . $product->id . '.');
        }

        $product->delete();

        if ($this->logging) {
            Log::info('Produto removido. Usuário: ' . $this->user->id . '; Produto: ' . json_encode($product) . '.');
        }

        return response()->json([
            'message' => 'Product deleted.',
            'deletedProduct' => $product,
        ]);
    }

    /**
     * Validate product name and brand uniqueness.
     *
     * @return void
     */
    private function validateUniqueNameAndBrand($request, $product = null)
    {
        $name = $request->name ?? $product->name;
        $brand = $request->brand ?? $product->brand;

        $matches = Product::when($product, function($query) use ($product) {
                $query->where('id', '!=', $product->id);
            })
            ->where('name', $name)
            ->where('brand', $brand)
            ->count();
        
        if ($matches > 0) {
            throw ValidationException::withMessages([
                'name' => ['Product with specified name and brand already exists.'],
                'brand' => ['Product with specified name and brand already exists.'],
            ]);
        }
    }
}
