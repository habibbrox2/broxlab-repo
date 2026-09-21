<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PostTaxonomy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AdminPageController extends Controller
{
    public function __construct(private PostTaxonomy $taxonomy) {}
    public function index(Request $r): View { $page=max(1,(int)$r->query('page',1)); $limit=max(5,min(100,(int)$r->query('limit',20))); $search=trim((string)$r->query('search','')); $sort=in_array($r->query('sort'),['id','title','created_at','updated_at'],true)?$r->query('sort'):'created_at'; $order=strtoupper($r->query('order','DESC'))==='ASC'?'ASC':'DESC'; $q=DB::table('pages'); if($search!=='')$q->where(fn($x)=>$x->where('title','like',"%$search%")->orWhere('content','like',"%$search%")); $total=$q->count(); $pages=$q->orderBy($sort,$order)->offset(($page-1)*$limit)->limit($limit)->get()->map(fn($x)=>(array)$x)->all(); return view('admin.pages.index',compact('pages','search','sort','order','limit')+['pagination'=>['current_page'=>$page,'total_pages'=>max(1,(int)ceil($total/$limit)),'total'=>$total,'from'=>($page-1)*$limit+1,'to'=>min($page*$limit,$total)]]); }
    public function create(): View { return $this->form(null); }
    public function edit(Request $r, ?int $id=null): View { return $this->form($this->page($id??(int)$r->query('id'))); }
    private function form(?object $item): View { $id=$item->id??0; return view('admin.pages.form',['title'=>$item?'Edit Page':'Add New Page','isCreate'=>!$item,'item'=>$item,'categories'=>DB::table('categories')->orderByDesc('id')->get(['id','name','slug'])->map(fn($x)=>(array)$x)->all(),'allTags'=>DB::table('tags')->orderByDesc('id')->get(['id','name','slug'])->map(fn($x)=>(array)$x)->all(),'selectedTags'=>$this->taxonomy->tagsForContent('page',$id),'selectedCategories'=>$this->taxonomy->categoriesForContent('page',$id),'status'=>!empty($item?->published)?'published':'draft']); }
    public function store(Request $r): RedirectResponse { $data=$this->data($r); if(!$data)return redirect('/admin/pages/create')->with('error','Failed to create page'); $id=DB::table('pages')->insertGetId($data); $this->tax($id,$r); $this->log('Page Created',$id,$data); return redirect('/admin/pages')->with('status','Page created successfully'); }
    public function update(Request $r, ?int $id=null): RedirectResponse { $id=$id??(int)$r->input('id'); $data=$this->data($r,$id); if(!$id||!$data)return redirect("/admin/pages/edit?id=$id")->with('error','Failed to update page'); DB::table('pages')->where('id',$id)->update($data); $this->tax($id,$r); $this->log('Page Updated',$id,$data); return redirect("/admin/pages/edit?id=$id")->with('status','Page updated successfully'); }
    public function show(Request $r, ?string $slug=null): View { $page=DB::table('pages')->where('slug',$slug??$r->query('slug'))->first(); abort_unless($page,404); return view('admin.pages.show',['page'=>$page,'tags'=>$this->taxonomy->tagsForContent('page',$page->id),'categories'=>$this->taxonomy->categoriesForContent('page',$page->id)]); }
    /** GET /admin/pages/delete/{id} — confirmation page (no side effects). */
    public function deleteConfirm(Request $r, ?int $id=null): View { $id=$id??(int)$r->query('id'); $page=$this->page($id); abort_unless($page,404); return view('admin.pages.delete',['page'=>$page]); }
    public function destroy(Request $r, ?int $id=null): RedirectResponse { $id=$id??(int)($r->input('id')??$r->query('id')); $page=$this->page($id); if(!$page)return redirect('/admin/pages')->with('error','Page not found'); DB::table('content_tags')->where(['content_type'=>'page','content_id'=>$id])->delete(); DB::table('content_categories')->where(['content_type'=>'page','content_id'=>$id])->delete(); DB::table('pages')->where('id',$id)->delete(); $this->log('Page Deleted',$id,['title'=>$page->title]); return redirect('/admin/pages')->with('status','Page deleted successfully'); }
    public function checkUrl(Request $r): JsonResponse { $q=DB::table('pages')->where('slug',trim((string)$r->query('slug',''))); if($r->filled('exclude_id'))$q->where('id','!=',(int)$r->query('exclude_id')); $ok=!$r->filled('slug')||!$q->exists(); return response()->json(['available'=>$ok,'success'=>$ok]); }
    private function data(Request $r,?int $id=null): ?array { $v=Validator::make($r->all(),['title'=>'required|string|max:500','content'=>'required|string','slug'=>'nullable|string|max:500','status'=>'nullable|in:draft,published'])->validated(); if(!$v)return null; $slug=trim($v['slug']??'') ?: strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$v['title']),'-')); $q=DB::table('pages')->where('slug',$slug);if($id)$q->where('id','!=',$id);if($q->exists())$slug.='-'.uniqid(); return ['title'=>$v['title'],'content'=>(new \HTMLPurifier(\HTMLPurifier_Config::createDefault()))->purify($v['content']),'slug'=>$slug,'published'=>($v['status']??'draft')==='published'?1:0]; }
    private function tax(int $id,Request $r): void { foreach(['content_tags'=>'tag_id','content_categories'=>'category_id'] as $table=>$column){DB::table($table)->where('content_type','page')->where('content_id',$id)->delete();foreach((array)$r->input($table==='content_tags'?'tags':'category_ids',[]) as $x)if(is_numeric($x))DB::table($table)->insert(['content_type'=>'page','content_id'=>$id,$column=>(int)$x]);} }
    private function page(int $id): ?object { return DB::table('pages')->where('id',$id)->first(); }
    private function log(string $action,int $id,array $details):void { DB::table('activity_logs')->insert(['user_id'=>auth()->id()??0,'role'=>'admin','action'=>$action,'resource_type'=>'page','resource_id'=>$id,'status'=>'success','ip_address'=>request()->ip(),'user_agent'=>substr((string)request()->userAgent(),0,500),'details'=>json_encode($details),'created_at'=>now(),'updated_at'=>now()]); }
}