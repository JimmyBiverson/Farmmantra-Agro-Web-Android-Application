@extends('layouts.app')
@section('title', 'Operations workspace')
@section('page-title', 'Operations workspace')
@section('content')
<div x-data="operationsWorkspace({{ \Illuminate\Support\Js::from(auth()->user()->role?->name) }})" x-init="load()" class="space-y-6">
    <section class="rounded-3xl p-6 md:p-8 text-white" style="background:linear-gradient(120deg,#214b38,#567447)">
        <p class="text-xs uppercase tracking-widest opacity-80 mb-3">FARMMANTRA · CONNECTED OPERATIONS</p>
        <h2 class="text-3xl font-bold mb-3" x-text="summary.greeting || 'Your work, clearly connected'"></h2>
        <p class="opacity-80">Review the next action, track every handover, and keep the operation moving.</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
            <template x-for="metric in metrics()" :key="metric.label"><div class="rounded-2xl p-4" style="background:#ffffff12"><p class="text-xs opacity-80" x-text="metric.label"></p><p class="font-bold text-xl mt-2" x-text="metric.value"></p></div></template>
        </div>
    </section>
    <div role="status" x-show="error" class="rounded-xl p-4" style="background:#fbe9e3;color:#7c392c" x-text="error"></div>
    <div class="flex flex-wrap gap-2">
        <template x-for="tab in tabs()" :key="tab.id"><button class="px-4 py-3 rounded-xl font-semibold text-sm" :style="active === tab.id ? 'background:#244c39;color:white':'background:var(--bg-card);color:var(--text-primary)'" @click="active=tab.id; page=1; loadRecords()" x-text="tab.label"></button></template>
        <button class="ml-auto px-4 py-3 rounded-xl text-sm border" @click="load()" :disabled="busy">Refresh</button>
    </div>
    <div class="flex flex-wrap gap-2" x-show="active==='requests'">
        <button class="btn-primary" @click="openForm('expense')">Post expense</button><button class="btn-primary" @click="openForm('funds')">Request funds</button>
        <button class="btn-primary" x-show="['Sales Head','Field Agronomist'].includes(role)" @click="openForm('travel')">Weekly travel plan</button>
        <button class="btn-primary" x-show="role==='System Administrator'" @click="openForm('budget')">Set monthly budget</button>
    </div>
    <div class="flex flex-wrap gap-2" x-show="role==='System Administrator'">
        <button class="btn-primary" x-show="active==='knowledge'" @click="openForm('knowledge')">Add guidance draft</button>
        <button class="btn-primary" x-show="active==='targets'" @click="openForm('target')">Set sales target</button>
        <button class="btn-primary" x-show="active==='zones'" @click="openForm('zone')">Add field boundary</button>
        <a class="btn-primary" x-show="active==='knowledge'" href="{{ route('web.admin.products') }}">Product photographs & pricing</a>
    </div>
    <div x-show="active==='reports'" class="grid md:grid-cols-2 gap-4">
        <template x-for="report in reports" :key="report.id"><a :href="base+'/export?report='+report.id" class="card-full p-6 flex items-center gap-4"><i class="fas fa-file-excel text-2xl" style="color:#438453"></i><div><h3 class="font-bold" x-text="report.label"></h3><p class="text-sm mt-1" style="color:var(--text-muted)">Download current records as an Excel workbook</p></div></a></template>
    </div>
    <div x-show="active!=='reports'" class="grid lg:grid-cols-2 gap-4">
        <template x-for="record in records" :key="record.id">
            <article class="card-full p-6 space-y-3">
                <div class="flex gap-3 items-start"><h3 class="font-bold flex-1" x-text="record.title || record.order_number || record.number || record.name || ('Target #'+record.id)"></h3><span class="px-3 py-1 text-xs font-bold rounded-full" style="background:#e6efdd;color:#244c39" x-text="record.review_stage || record.status || (active==='knowledge'?(record.published?'Published':'Draft'):active==='targets'?(record.period||'monthly'):'Active')"></span></div>
                <p class="text-sm" style="color:var(--text-secondary)" x-text="record.franchise?.name || record.user?.name || record.crop || ''"></p>
                <p class="font-bold text-xl" x-show="record.amount || record.total_amount || record.target_amount" x-text="money(record.amount || record.total_amount || record.target_amount)"></p>
                <template x-for="line in (record.items || [])"><p class="text-sm" x-text="(line.product_name || line.product?.name || 'Product')+' · '+line.quantity+' × '+money(line.unit_price)"></p></template>
                <p class="text-sm whitespace-pre-wrap" x-text="record.reason || record.details?.notes || record.notes || ''"></p>
                <p class="text-sm" x-show="record.details?.itinerary" x-text="record.details?.itinerary"></p>
                <p class="text-sm" x-show="active==='targets'" x-text="(record.starts_on || (record.month+'/'+record.year))+' → '+(record.ends_on || 'Month end')+' · '+(record.achievement_percentage??'—')+'% achieved'"></p>
                <p class="text-sm" x-show="active==='zones'" x-text="record.radius_meters+' m radius · '+record.latitude+', '+record.longitude"></p>
                <img x-show="record.image_url" :src="record.image_url" class="rounded-xl max-h-48 object-cover" alt="Client supplied photograph">
                <div x-show="active==='knowledge'" class="text-sm space-y-2"><template x-for="entry in Object.entries(record.content || {})"><p><strong x-text="entry[0].replaceAll('_',' ')+': '"></strong><span x-text="entry[1]"></span></p></template></div>
                <a x-show="record.details?.receipt" :href="base+'/requests/'+record.id+'/receipt'" class="text-sm underline" target="_blank" rel="noopener">View expense receipt</a>
                <div class="flex gap-2 flex-wrap"><template x-for="(photo,index) in (record.photos || [])"><a :href="base+'/returns/'+record.id+'/photos/'+index" target="_blank" rel="noopener" class="text-sm underline" x-text="'Return photo '+(index+1)"></a></template></div>
                <template x-for="entry in (record.history || record.review_history || [])"><p class="text-xs border-t pt-2" style="color:var(--text-muted)" x-text="(entry.name||'')+' · '+(entry.action||entry.stage||'')+' · '+entry.notes+(entry.reference?' · '+entry.reference:'')"></p></template>
                <div class="flex flex-wrap gap-2 pt-2" x-show="canReview(record)"><button class="px-4 py-2 border rounded-xl" :disabled="busy" @click="startReview(record,false)">Decline</button><button class="btn-primary" :disabled="busy" @click="startReview(record,true)" x-text="active==='returns' && record.status==='warehouse'?'Confirm physically received':'Approve & forward'"></button></div>
                <button class="btn-primary" x-show="active==='requests' && record.type==='funds' && record.status==='approved' && role==='Finance Department'" :disabled="busy" @click="startReview(record,true,true)">Record funds disbursed</button>
                <button class="btn-primary" x-show="active==='knowledge' && role==='System Administrator'" @click="editArticle(record)">Edit / publish guidance</button>
            </article>
        </template>
        <div x-show="!busy && records.length===0" class="card-full p-8 text-center lg:col-span-2"><h3 class="font-bold text-xl">You’re all caught up</h3><p class="mt-2 text-sm" style="color:var(--text-muted)">Records will appear here as work is submitted. Knowledge and field locations remain unpublished until approved content is supplied.</p></div>
    </div>
    <div class="flex gap-3" x-show="lastPage>1"><button class="px-4 py-2 border rounded-xl" :disabled="page<=1 || busy" @click="page--;loadRecords()">Previous</button><span class="py-2" x-text="'Page '+page+' of '+lastPage"></span><button class="px-4 py-2 border rounded-xl" :disabled="page>=lastPage || busy" @click="page++;loadRecords()">Next</button></div>
    <template x-teleport="body"><div x-show="formType" x-cloak class="modal-overlay" @keydown.escape.window="formType=''" @click.self="formType=''">
        <section class="modal-panel"><div class="flex items-start gap-4 mb-6"><div class="flex-1"><h3 class="text-xl font-bold" x-text="formTitle()"></h3><p class="text-sm mt-2" style="color:var(--text-muted)">Every submission keeps an audit trail.</p></div><button @click="formType=''" aria-label="Close dialog">✕</button></div>
        <form @submit.prevent="submitForm($event.target)" class="space-y-4">
            <template x-for="field in fields()" :key="field.key"><div><label class="block text-sm font-semibold mb-2" :for="'ops-'+field.key" x-text="field.label"></label>
                <template x-if="!field.options"><input :id="'ops-'+field.key" :type="field.type||'text'" :name="field.key" :required="field.required!==false" x-model="form[field.key]" class="w-full"></template>
                <template x-if="field.options"><select :id="'ops-'+field.key" :name="field.key" :required="field.required!==false" x-model="form[field.key]" class="w-full"><option value="">Select…</option><template x-for="option in field.options"><option :value="option.id" x-text="option.name"></option></template></select></template>
            </div></template>
            <div x-show="formType==='knowledge'"><label class="block mb-2 text-sm font-semibold">Client-approved photograph</label><input type="file" name="image" accept="image/*"><label class="flex gap-3 mt-4 items-center"><input type="checkbox" x-model="form.published"> Publish approved guidance (all application fields required)</label></div>
            <div x-show="formType==='expense'"><label class="block mb-2 text-sm font-semibold">Receipt photograph / PDF</label><input type="file" name="receipt" accept="image/*,.pdf"></div>
            <p x-show="formError" role="alert" style="color:#a34b34" x-text="formError"></p>
            <div class="modal-footer flex justify-end gap-3 pt-4"><button type="button" @click="formType=''" class="px-4 py-3 border rounded-xl">Cancel</button><button class="btn-primary" :disabled="busy" x-text="busy?'Saving…':'Save & submit'"></button></div>
        </form></section>
    </div></template>
</div>
@endsection
@push('scripts')
<script>
function operationsWorkspace(role) {
 return {role,base:@json(url('/workspace/data')),active:'requests',records:[],summary:{},options:{},page:1,lastPage:1,busy:false,error:'',formType:'',form:{},formError:'',review:null,
 reports:(role==='Franchise Partner'?[{id:'franchise_stock',label:'My franchise stock'}]:[{id:'warehouse',label:'Central warehouse stock'},{id:'franchise_stock',label:'Franchise stock'},{id:'outstanding',label:'Outstanding & payment ageing'},{id:'sales',label:'Combined sales & margins'},{id:'expenses',label:'Expense register'}]),
 money(value){return 'UGX '+Number(value||0).toLocaleString()},
 metrics(){return [{label:'Order reviews',value:this.summary.pending_order_reviews??'—'},{label:'Pending requests',value:this.summary.pending_requests??'—'},{label:'Monthly expenses',value:this.money(this.summary.monthly_expenses)},{label:'Budget used',value:this.summary.budget_used_percentage==null?'—':this.summary.budget_used_percentage+'%'}]},
 tabs(){return [{id:'requests',label:'Expenses & funds'},...(['Sales Head','System Administrator','Warehouse'].includes(role)?[{id:'orders',label:'Order reviews'},{id:'returns',label:'Goods returns'}]:role==='Franchise Partner'?[{id:'returns',label:'Goods returns'}]:[]),{id:'knowledge',label:'Knowledge'},...(['Sales Head','System Administrator','Franchise Partner'].includes(role)?[{id:'targets',label:'Targets'}]:[]),...(['System Administrator','Field Agronomist'].includes(role)?[{id:'zones',label:'Field locations'}]:[]),...(['System Administrator','Finance Department','Sales Head','Franchise Partner'].includes(role)?[{id:'reports',label:'Excel reports'}]:[])]},
 async request(path,init={}){const response=await fetch(this.base+path,{...init,headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,...init.headers}});const data=await response.json();if(!response.ok)throw Error(Object.values(data.errors||{}).flat()[0]||data.message||'Unable to complete request.');return data},
 async load(){try{this.summary=(await this.request('/dashboard')).data;if(['System Administrator','Sales Head','Finance Department','Warehouse'].includes(role)){this.options=(await this.request('/options')).data}}catch(e){this.error=e.message}await this.loadRecords()},
 async loadRecords(){if(this.active==='reports')return;this.busy=true;this.error='';try{const data=await this.request('/'+this.active+'?page='+this.page+(this.active==='orders'?'&status=pending':''));this.records=Array.isArray(data.data)?data.data:data.data?.data||[];this.lastPage=data.last_page||data.data?.last_page||1}catch(e){this.error=e.message;this.records=[]}finally{this.busy=false}},
 canReview(r){if(this.active==='orders')return (r.review_stage==='sales_head'&&role==='Sales Head')||(r.review_stage==='admin'&&role==='System Administrator');if(this.active==='returns')return (r.status==='sales_head'&&role==='Sales Head')||(r.status==='admin'&&role==='System Administrator')||(r.status==='warehouse'&&role==='Warehouse');return this.active==='requests'&&r.status==='submitted'&&r.user_id!==@json(auth()->id())&&((role==='System Administrator'&&['funds','travel'].includes(r.type))||(role==='Finance Department'&&r.type==='expense'))},
 openForm(type){this.formType=type;this.formError='';this.form={entry_date:new Date().toISOString().slice(0,10),published:false};this.review=null},
 editArticle(record){this.openForm('knowledge');this.form={...record,...record.content};this.form.category_id=String(record.category_id);this.form.product_id=String(record.product_id||'')},
 startReview(record,approve,pay=false){this.formType='review';this.form={};this.formError='';this.review={record,approve,pay}},
 formTitle(){return this.formType==='review'?(this.review?.pay?'Record funds disbursed':'Review submission'):this.formType==='knowledge'?'Knowledge editor':this.formType==='target'?'Set a sales target':this.formType==='zone'?'Configure field boundary':this.formType==='travel'?'Weekly travel plan':'New '+this.formType},
 fields(){const f=(key,label,type='text',required=true)=>({key,label,type,required}),s=(key,label,options,required=true)=>({key,label,options:options||[],required});if(this.formType==='review')return [f('notes','Review notes'),...(this.review?.pay?[f('reference','Bank / payment reference')]:[])];if(this.formType==='knowledge')return [s('kind','Library',[{id:'crop',name:'Crop knowledge'},{id:'product',name:'Product knowledge'}]),f('crop','Crop name','text',false),s('category_id','Category',this.options.categories),s('product_id','Recommended product',this.options.products,false),f('title','Disease / pest / product title'),...['active_ingredient','dosage','stage','method','dos','donts','phi','notes'].map(key=>f(key,key.replaceAll('_',' '),'text',false))];if(this.formType==='target')return [s('franchise_id','Franchise',this.options.franchises),s('user_id','Sales Head',this.options.users,false),s('product_id','Product',this.options.products,false),s('period','Period',['weekly','monthly','yearly'].map(id=>({id,name:id}))),f('target_amount','Target (UGX)','number'),f('starts_on','Start date','date'),f('ends_on','End date','date')];if(this.formType==='zone')return [f('name','Location name'),f('latitude','Latitude'),f('longitude','Longitude'),f('radius_meters','Radius (meters)','number'),s('user_id','Assigned field user',this.options.users,false)];return [f('title','Purpose / expense title'),...(this.formType!=='travel'?[f('amount','Amount (UGX)','number')]:[]),f('entry_date','Entry date','date'),f('notes','Explanation / category'),...(this.formType==='travel'?[f('week_start','Travel week starting Sunday','date'),f('itinerary','Destinations and activities')]:[])]},
 async submitForm(element){this.busy=true;this.formError='';try{let path,body=new FormData(element);if(this.formType==='review'){path='/'+this.active+'/'+this.review.record.id+'/review';body=new FormData();if(this.active==='requests'){body.set('action',this.review.pay?'pay':this.review.approve?'approve':'reject')}else body.set('approve',this.review.approve?'1':'0');body.set('notes',this.form.notes||'');body.set('reference',this.form.reference||'')}
 else if(this.formType==='knowledge'){path='/knowledge'+(this.form.id?'/'+this.form.id:'');body.set('published',this.form.published?'1':'0');for(const key of ['active_ingredient','dosage','stage','method','dos','donts','phi','notes']){body.delete(key);body.set('content['+key+']',this.form[key]||'')}}
 else if(this.formType==='target'){path='/targets'}else if(this.formType==='zone'){path='/zones'}else{path='/requests';body.set('type',this.formType);for(const key of ['notes','week_start','itinerary']){body.delete(key);if(this.form[key])body.set('details['+key+']',this.form[key])}}for(const [key,value]of [...body.entries()])if(value instanceof File&&!value.size)body.delete(key);await this.request(path,{method:'POST',body});this.formType='';await this.load()}catch(e){this.formError=e.message}finally{this.busy=false}}
 }
}
</script>
@endpush
