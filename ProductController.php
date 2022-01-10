<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\Base;

use App\Models\Product;
use Validator;

class ProductController extends Controller
{
    public function index()
    {
        $data =  Product::with('category_info')->get()->toArray();

        foreach ($data as $i => $item) {
            $data[$i]['photos']  = (Array)json_decode(stripslashes($item['photos']));
            $data[$i]['category_info']  = $item['category_info']['name'];
            $data[$i]['doc_info']  = (Array)json_decode(stripslashes($item['doc_info']));
        }


        return Base::touser($data, true);
    }


    public function store(Request $request)
    {
        $data = $request->input('data');

        $rules = [
'name' => 'required|unique:products',
'price' => 'required|numeric',
'unit' => 'required',
'quantity' => 'required|numeric',
'desc' => 'required',
'category' => 'required|numeric|exists:category,id',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }
      




        $product = new Product;
        $data = $request->input('data');
        $product->name = $data['name'];
        $product->price = $data['price'];
        $product->unit = $data['unit'];
        $product->quantity = $data['quantity'];
        $product->desc = $data['desc'];
        if ($data['photos'] !== null) {
            $product->photos = json_encode($data['photos']);
        }

        if ($data['doc_info'] !== null) {
            $product->doc_info = json_encode($data['doc_info']);
        }

       
        $product->category = $data['category'];
        $product->save();

        return Base::touser('Product Created', true);
    }

 
    public function show($id)
    {
        $array =  Product::where('product_id', '=', $id)->first();


        $array['photos']  = (Array)json_decode(stripslashes($array['photos']));

        $array['doc_info']  = (Array)json_decode(stripslashes($array['doc_info']));


        return Base::touser($array, true);
    }


   
    public function update(Request $request, $id)
    {
        $data = $request->input('data');

        $rules = [

'name' => 'required|unique:products,name,'.$id.',product_id',
'price' => 'required|numeric',
'unit' => 'required',
'quantity' => 'required|numeric',
'desc' => 'required',
'category' => 'required|numeric|exists:category,id',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return Base::touser($validator->errors()->all()[0]);
        }
      

        $product = new Product;
        $product = $product->where('product_id', '=', $id)->first();
        $data = $request->input('data');
        $product->name = $data['name'];
        $product->price = $data['price'];
        $product->unit = $data['unit'];
        $product->quantity = $data['quantity'];
        $product->desc = $data['desc'];

        if ($data['photos'] !== null) {
            $product->photos = json_encode($data['photos']);
        }

        if ($data['doc_info'] !== null) {
            $product->doc_info = json_encode($data['doc_info']);
        }

        
        $product->category = $data['category'];

        $product->save();

        return Base::touser('Product Updated', true);
    }

    
    public function destroy($id)
    {


         try {

            
        
        $api = new Product();

        $api = $api->where('product_id', '=', $id)->first();

        $api->delete();

        return Base::touser('Product Deleted', true);
        } catch (\Exception $e) {

            return Base::touser("Can't able to delete Product its connected to Other Data !");
            //return Base::throwerror();
        }


    }
}
