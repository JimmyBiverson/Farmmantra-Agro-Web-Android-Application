<section class="card-full p-4 space-y-4" x-data="outletWork" x-init="start()">
<h2 class="font-bold text-lg">{{ $office ? 'Direct office sales' : 'Weekly physical stock check' }}</h2>
<div role="status" x-text="feedback"></div>
@if(!$office)
<label>Franchise <select x-model="outlet" @change="inventory()" :disabled="saving"><option value="">Choose an outlet</option><template x-for="f in outlets"><option :value="f.id" x-text="f.name"></option></template></select></label>
@else
<label>Price list <select x-model="tier"><option value="farmer">Farmer</option><option value="retailer">Retailer</option></select></label>
<label>Payment <select x-model="method"><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="mobile_money">Mobile money</option></select></label>
@endif
<form class="space-y-3" @submit.prevent="submit()"><template x-for="line in lines" :key="line.product_id"><fieldset class="rounded-xl border p-3"><legend x-text="line.name+' · '+line.sku"></legend><p x-text="'System stock: '+line.quantity+' pieces'"></p>
<label>{{ $office ? 'Quantity sold' : 'Counted pieces' }} <input min="0" step="1" type="number" x-model="line.count" @if(!$office)required @endif></label>
@if(!$office)<template x-if="line.count!=='' && Number(line.count)!==Number(line.quantity)"><label><span x-text="'Difference: '+(line.count-line.quantity)+' · Reason'"></span><input maxlength="1000" required x-model="line.remark"></label></template>@endif
</fieldset></template><button x-show="lines.length" :disabled="saving" class="btn-primary">{{ $office ? 'Record sale' : 'Submit stock count' }}</button></form>
</section>
<script>
document.addEventListener('alpine:init',()=>Alpine.data('outletWork',()=>({
 office:@json($office), outlet:'', outlets:[], lines:[], snapshot:null, tier:'farmer', method:'cash',feedback:'',saving:false,
 async call(path,data){const response=await fetch(@json(url('/workspace/data'))+'/'+path,{method:data?'POST':'GET',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},...(data?{body:JSON.stringify(data)}:{})});const result=await response.json();if(!response.ok)throw Error(Object.values(result.errors||{}).flat()[0]||result.message);return result},
 async start(){try{if(this.office)await this.inventory();else this.outlets=(await this.call('stock-checks/outlets')).data}catch(e){this.feedback=e.message}},
 async inventory(){const outlet=this.outlet;this.lines=[];this.snapshot=null;if(!this.office&&!outlet)return;try{const r=await this.call(this.office?'office-stock':'stock-checks/outlets/'+outlet);if(outlet!==this.outlet)return;this.snapshot=r.snapshot;this.lines=r.data.map(row=>({...row,count:'',remark:''}));this.feedback=this.lines.length?'':'No inventory for this outlet.'}catch(e){this.feedback=e.message}},
 async submit(){if(this.saving)return;this.saving=true;try{if(this.office){await this.call('office-sales',{payment_method:this.method,price_tier:this.tier,items:this.lines.filter(row=>Number(row.count)>0).map(row=>({product_id:row.product_id,quantity:Number(row.count)}))})}else{await this.call('stock-checks',{snapshot:this.snapshot,counted_at:new Date().toISOString(),lines:this.lines.map(row=>({product_id:row.product_id,count:Number(row.count),remark:row.remark}))})}await this.inventory();this.feedback='Recorded successfully.'}catch(e){this.feedback=e.message}finally{this.saving=false}}
})));
</script>
