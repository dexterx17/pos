<?php
class Receiving_lib
{
	var $CI;

  	function __construct()
	{
		$this->CI =& get_instance();
	}

	function get_cart()
	{
		if(!$this->CI->session->userdata('cartRecv'))
			$this->set_cart(array());

		return $this->CI->session->userdata('cartRecv');
	}

	function set_cart($cart_data)
	{
		$this->CI->session->set_userdata('cartRecv',$cart_data);
	}

	function get_supplier()
	{
		if(!$this->CI->session->userdata('supplier'))
			$this->set_supplier(-1);

		return $this->CI->session->userdata('supplier');
	}

	function set_supplier($supplier_id)
	{
		$this->CI->session->set_userdata('supplier',$supplier_id);
	}

	function get_mode()
	{
		if(!$this->CI->session->userdata('recv_mode'))
			$this->set_mode('receive');

		return $this->CI->session->userdata('recv_mode');
	}

	function set_mode($mode)
	{
		$this->CI->session->set_userdata('recv_mode',$mode);
	}
	
	//Para que venda en un almacen determinado
	function get_almacen()
	{
		if(!$this->CI->session->userdata('recv_almacen'))
			$this->set_almacen('-1');

		return $this->CI->session->userdata('recv_almacen');
	}

	function set_almacen($almacen)
	{
		$this->CI->session->set_userdata('recv_almacen',$almacen);
	}

	function add_item($item_id,$quantity=1,$discount=0,$price=null,$tax=null,$description=null,$serialnumber=null)
	{
		//make sure item exists in database.
		if(!$this->CI->Item->exists($item_id))
		{
			//try to get item id given an item_number
			$item_id = $this->CI->Item->get_item_id($item_id);

			if(!$item_id)
				return false;
		}

		//Get items in the receiving so far.
		$items = $this->get_cart();

        //We need to loop through all items in the cart.
        //If the item is already there, get it's key($updatekey).
        //We also need to get the next key that we are going to use in case we need to add the
        //item to the list. Since items can be deleted, we can't use a count. we use the highest key + 1.

        $maxkey=0;                       //Highest key so far
        $itemalreadyinsale=FALSE;        //We did not find the item yet.
		$insertkey=0;                    //Key to use for new entry.
		$updatekey=0;                    //Key to use to update(quantity)

		foreach ($items as $item)
		{
            //We primed the loop so maxkey is 0 the first time.
            //Also, we have stored the key in the element itself so we can compare.
            //There is an array function to get the associated key for an element, but I like it better
            //like that!

			if($maxkey <= $item['line'])
			{
				$maxkey = $item['line'];
			}

			if($item['item_id']==$item_id)
			{
				$itemalreadyinsale=TRUE;
				$updatekey=$item['line'];
			}
		}

		$insertkey=$maxkey+1;

		//array records are identified by $insertkey and item_id is just another field.
		$item = array(($insertkey)=>
		array(
			'item_id'=>$item_id,
			'line'=>$insertkey,
			'name'=>$this->CI->Item->get_info($item_id)->name,
			'description'=>$description!=null ? $description: $this->CI->Item->get_info($item_id)->description,
			'serialnumber'=>$serialnumber!=null ? $serialnumber: '',
			'allow_alt_description'=>$this->CI->Item->get_info($item_id)->allow_alt_description,
			'is_serialized'=>$this->CI->Item->get_info($item_id)->is_serialized,
			'quantity'=>$quantity,
            'discount'=>$discount,
			'price'=>$price!=null ? $price: $this->CI->Item->get_info($item_id)->cost_price
			)
		);

		//Item already exists
		if($itemalreadyinsale)
		{
			$items[$updatekey]['quantity']+=$quantity;
		}
		else
		{
			//add to existing array
			$items+=$item;
		}

		$this->set_cart($items);
		return true;

	}

	function edit_item($line,$description,$serialnumber,$quantity,$discount,$price)
	{
		$items = $this->get_cart();
		if(isset($items[$line]))
		{
			$items[$line]['description'] = $description;
			$items[$line]['serialnumber'] = $serialnumber;
			$items[$line]['quantity'] = $quantity;
			$items[$line]['discount'] = $discount;
			$items[$line]['price'] = $price;
			$this->set_cart($items);
		}

		return false;
	}

	function is_valid_receipt($receipt_receiving_id)
	{
		//RECV #
		$pieces = explode(' ',$receipt_receiving_id);

		if(count($pieces)==2)
		{
			return $this->CI->Receiving->exists($pieces[1]);
		}

		return false;
	}

	function return_entire_receiving($receipt_receiving_id)
	{
		//POS #
		$pieces = explode(' ',$receipt_receiving_id);
		$receiving_id = $pieces[1];

		$this->empty_cart();
		$this->delete_supplier();

		foreach($this->CI->Receiving->get_receiving_items($receiving_id)->result() as $row)
		{
			$this->add_item($row->item_id,-$row->quantity_purchased,$row->discount_percent,$row->item_unit_price,null,$row->description,$row->serialnumber);
		}
		$this->set_supplier($this->CI->Receiving->get_supplier($receiving_id)->person_id);
	}

	function copy_entire_receiving($receiving_id)
	{
		$this->empty_cart();
		$this->delete_supplier();

		foreach($this->CI->Receiving->get_receiving_items($receiving_id)->result() as $row)
		{
			$this->add_item($row->item_id,$row->quantity_purchased,$row->discount_percent,$row->item_unit_price,null,$row->description,$row->serialnumber);
		}
		foreach($this->CI->Receiving->get_receiving_payments($receiving_id)->result() as $row)
		{
			//$this->add_payment($row->payment_type,$row->payment_amount);
			$this->add_payment($row->payment_id,$row->payment_amount,$row->payment_type);
		}
		$this->set_supplier($this->CI->Receiving->get_supplier($receiving_id)->person_id);

	}

	function delete_item($line)
	{
		$items=$this->get_cart();
		unset($items[$line]);
		$this->set_cart($items);
	}

	function empty_cart()
	{
		$this->CI->session->unset_userdata('cartRecv');
	}

	function delete_supplier()
	{
		$this->CI->session->unset_userdata('supplier');
	}

	function clear_mode()
	{
		$this->CI->session->unset_userdata('receiving_mode');
	}

	function clear_all()
	{
		$this->clear_mode();
		$this->empty_cart();
		$this->delete_supplier();
		//Alain Multiple Payments
		$this->empty_payments();
	}

	function get_taxes()
	{
		$taxes = array();
		foreach($this->get_cart() as $line=>$item)
		{
			$tax_info = $this->CI->Item_taxes->get_info($item['item_id']);

			foreach($tax_info as $tax)
			{
				$name = $tax['percent'].'% ' . $tax['name'];
				$tax_amount=($item['price']*$item['quantity']-$item['price']*$item['quantity']*$item['discount']/100)*(($tax['percent'])/100);


				if (!isset($taxes[$name]))
				{
					$taxes[$name] = 0;
				}
				$taxes[$name] += $tax_amount;
			}
		}

		return $taxes;
	}

	function get_subtotal()
	{
		$subtotal = 0;
		foreach($this->get_cart() as $item)
		{
		    $subtotal+=($item['price']*$item['quantity']-$item['price']*$item['quantity']*$item['discount']/100);
		}
		return $subtotal;
	}

	function get_total_old()
	{
		$total = 0;
		foreach($this->get_cart() as $item)
		{
            $total+=($item['price']*$item['quantity']-$item['price']*$item['quantity']*$item['discount']/100);
		}

		foreach($this->get_taxes() as $tax)
		{
			$total+=$tax;
		}

		return $total;
	}
	function get_total()
	{
		$total = 0;
		foreach($this->get_cart() as $item)
		{
            $total+=($item['price']*$item['quantity']-$item['price']*$item['quantity']*$item['discount']/100);
		}
		
		return $total;
	}
	
	//yo
	//Alain Multiple Payments
	function get_payments()
	{
		if(!$this->CI->session->userdata('payments_rec'))
			$this->set_payments(array());

		return $this->CI->session->userdata('payments_rec');
	}

	//Alain Multiple Payments
	function set_payments($payments_data)
	{
		$this->CI->session->set_userdata('payments_rec',$payments_data);
	}

	//Alain Multiple Payments
	function add_payment($payment_id,$payment_amount,$payment_type)
	{
		$payments=$this->get_payments();
		$payment = array($payment_id=>
		array(
			'payment_id'=>$payment_id,
			'payment_type'=>$payment_type,
			'payment_amount'=>$payment_amount
			)
		);

		//payment_method already exists, add to payment_amount
		if(isset($payments[$payment_id]))
		{
			$payments[$payment_id]['payment_amount']+=$payment_amount;
		}
		else
		{
			//add to existing array
			$payments+=$payment;
		}

		$this->set_payments($payments);
		return true;
	}
	
	//Alain Multiple Payments
	function edit_payment($payment_id,$payment_amount)
	{
		$payments = $this->get_payments();
		if(isset($payments[$payment_id]))
		{
			//$payments[$payment_id]['payment_type'] = $payment_id;
			$payments[$payment_id]['payment_amount'] = $payment_amount;
			$this->set_payments($payment_id);
		}
		return false;
	}

	//Alain Multiple Payments
	function delete_payment($payment_id)
	{
		$payments=$this->get_payments();
		unset($payments[$payment_id]);
		$this->set_payments($payments);
	}

	//Alain Multiple Payments
	function empty_payments()
	{
		$this->CI->session->unset_userdata('payments_rec');
	}

	//Alain Multiple Payments
	function get_payments_total()
	{
		$subtotal = 0;
		foreach($this->get_payments() as $payments)
		{
		    $subtotal+=$payments['payment_amount'];
		}
		return to_currency_no_money($subtotal);
	}

	//Alain Multiple Payments
	function get_amount_due()
	{
		$amount_due=0;
		$payment_total = $this->get_payments_total();
		$sales_total=$this->get_total();
		$amount_due=to_currency_no_money($sales_total - $payment_total);
		return $amount_due;
	}
}
?>