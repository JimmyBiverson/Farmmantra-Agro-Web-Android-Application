@extends('layouts.app')
@section('title','Request goods return')
@section('page-title','Request goods return')
@section('content')
<section class="card-full p-4 space-y-4" x-data="goodsReturnForm">
<p role="status" x-text="message"></p>
<label class="block">Received order<select x-model="selected" @change="counts={}" class="w-full"><option value="">Choose a received order</option><template x-for="o in orders" :key="o.id"><option :value="o.id" x-text="o.order_number"></option></template></select></label>
<button class="btn-primary" x-show="more" :disabled="busy" @click="load()">Load older orders</button>
<form x-show="order" class="space-y-4" @submit.prevent="send($el)">
<template x-for="line in order?.items || []" :key="line.id"><label class="block border rounded-lg p-3">
<strong x-text="line.product?.name"></strong>
<span class="block" x-text="'UGX '+Number(line.unit_price).toLocaleString()+' · Batch '+(line.dispatch_lot?.batch || 'unrecorded')+' · Expiry '+(line.dispatch_lot?.expiry || 'unrecorded')"></span>
<span x-text="'Received: '+received(line.product_id)+' · Quantity to return'"></span>
<input type="number" step="0.01" min="0" :max="received(line.product_id)" x-model="counts[line.product_id]" placeholder="0"></label></template>
<label class="block">Reason<textarea class="w-full" name="reason" required maxlength="3000"></textarea></label>
<label class="block">Mode of transport<input class="w-full" name="transport" required maxlength="255"></label>
<label class="block">Photographs (1–5)<input class="w-full" name="photos[]" type="file" accept="image/*" multiple required></label>
<button class="btn-primary" :disabled="busy">Submit for review</button>
</form></section>
<script>
document.addEventListener('alpine:init',()=>Alpine.data('goodsReturnForm',()=>({
    orders:[],counts:{},selected:'',page:0,more:true,busy:false,message:'',
    init(){this.load()},
    get order(){return this.orders.find(o=>String(o.id)===String(this.selected))},
    received(id){return (this.order?.stock_receipt?.items||[]).filter(r=>r.product_id===id).reduce((sum,r)=>sum+Number(r.received_quantity),0)},
    async request(path,options={}){
        const response=await fetch(@json(url('/workspace/data/returns'))+path,{...options,headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}});
        const body=await response.json();
        if(!response.ok)throw Error(Object.values(body.errors||{}).flat()[0]||body.message);
        return body;
    },
    async load(){this.busy=true;try{const {data}=await this.request('/eligible-orders?page='+(this.page+1));this.orders.push(...data.data);this.page=data.current_page;this.more=this.page<data.last_page;this.message=this.orders.length?'':'No received orders available.'}catch(e){this.message=e.message}finally{this.busy=false}},
    async send(form){
        if(this.busy)return;
        const lines=Object.entries(this.counts).filter(([,qty])=>Number(qty)>0);
        if(!lines.length){this.message='Enter a quantity for at least one received item.';return}
        this.busy=true;
        try{const data=new FormData(form);data.set('order_id',this.selected);lines.forEach(([id,qty],i)=>{data.set('items['+i+'][product_id]',id);data.set('items['+i+'][quantity]',qty)});
        const result=await this.request('',{method:'POST',body:data});form.reset();this.counts={};this.selected='';this.message='Return '+result.data.number+' submitted for sales head review.'}catch(e){this.message=e.message}finally{this.busy=false}
    }
})));
</script>
@endsection
