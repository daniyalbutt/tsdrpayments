<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    // STRIPE = 0, FETCH = 3, AUTHORIZE = 4, PAYPAL = 5, SQUARE = 6, PAYKINGS / TG = 7

    use HasFactory;

    protected $guarded = [];

    public function client(){
        return $this->hasOne(Client::class, 'id', 'client_id');
    }

    public function get_status(){
        $status = $this->status;
        if($status == 0){
            return 'PENDING';
        }elseif($status == 1){
            return 'DECLINED';
        }elseif($status == 2){
            return 'SUCCESS';
        }
    }

    public function get_badge_status(){
        $status = $this->status;
        if($status == 0){
            return 'btn-warning';
        }elseif($status == 1){
            return 'btn-danger';
        }elseif($status == 2){
            return 'btn-success';
        }
    }
    public function get_badge_invoice_status(){
        $status = $this->status;
        if($status == 0){
            return 'badge text-bg-warning text-white';
        }elseif($status == 1){
            return 'badge text-bg-danger text-white';
        }elseif($status == 2){
            return 'badge text-bg-success text-white';
        }
    }
    
    public function getCard(){
        if($this->merchants == null) return '';

        $merchant = $this->merchants->merchant;

        if($merchant == 0){
            $response = json_decode($this->return_response);
            if(!$response || !isset($response->payment_method_details->card)) return '';
            return strtoupper($response->payment_method_details->card->brand) 
                . ' **** **** **** ' 
                . $response->payment_method_details->card->last4;

        } elseif($merchant == 4){
            $data = json_decode($this->payment_data);
            if(!$data || !isset($data->cc_number)) return '';
            return '**** **** **** ' . substr($data->cc_number, -4);

        } elseif($merchant == 3){
            $data = json_decode($this->payment_data);
            if(!$data || !isset($data->cardnumber)) return '';
            return '**** **** **** ' . substr($data->cardnumber, -4);
        }

        return '';
    }
    
    public function getCardBrand(){
        if($this->merchants == null) return '';
        
        $merchant = $this->merchants->merchant;

        if($merchant == 0){
            $response = json_decode($this->return_response);
            if(!$response || !isset($response->payment_method_details->card->brand)) return '';
            return strtoupper($response->payment_method_details->card->brand);

        } elseif($merchant == 4){
            $response = json_decode($this->authorize_response);
            if(!$response || !isset($response->card_brand)) return '';
            return strtoupper($response->card_brand);

        } elseif($merchant == 3){
            return 'FETCH';
        }

        return '';
    }
    
    public function getMerchant(){
        if($this->merchant == 0){
            return 'STRIPE';
        }else if($this->merchant == 3){
            return 'FETCH';
        }else if($this->merchant == 4){
            return 'AUTHORIZE';
        }else if($this->merchant == 5){
            return 'PAYPAL';
        }else if($this->merchant == 6){
            return 'SQUARE';
        }else if($this->merchant == 7){
            return 'PAYKINGS / TG';
        }
    }

    public function merchants(){
        return $this->hasOne(Merchant::class, 'id', 'merchant');
    }

}
