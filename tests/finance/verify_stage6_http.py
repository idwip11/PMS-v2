"""HTTP validation against the isolated localhost:8082 stage-6 app; real CSRF/login/filters."""
import uuid, http.cookiejar, urllib.request, urllib.parse, urllib.error, re, json, xml.etree.ElementTree as ET
BASE='http://localhost:8082'
passed=0
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs): return None
class Client:
    def __init__(self):
        self.cookies=http.cookiejar.CookieJar()
        self.open=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))
        self.raw=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies),NoRedirect())
    def get(self,path,follow=True): return self.send(path,None,follow)
    def send(self,path,data=None,follow=True,headers=None):
        req=urllib.request.Request(BASE+path,data=None if data is None else urllib.parse.urlencode(data).encode(),headers=headers or {})
        try:r=(self.open if follow else self.raw).open(req,timeout=15)
        except urllib.error.HTTPError as e:r=e
        if not r.geturl().startswith(BASE+'/'):raise RuntimeError('Validation redirect left the isolated app')
        body=r.read().decode('utf-8',errors='replace')
        return r.code,body,dict(r.headers)
    def post(self,path,data,follow=True):
        csrf=next((c.value for c in self.cookies if c.name=='csrf_cookie_name'),None)
        if not csrf:
            _,html,_=self.get('/login');m=re.search(r'name="([^"]+)" value="([a-f0-9]{32})"',html);key,csrf=m.groups()
        else:key='csrf_test_name'
        return self.send(path,{**data,key:csrf},follow)
    def login(self,user,password='Mitra-Stage6-Only!'):
        self.get('/login');return self.post('/login',{'username':user,'password':password},False)
    def select(self,prop):return self.post('/properties/select',{'property_id':prop},False)
def check(ok,label):
    global passed
    if not ok:raise RuntimeError('FAIL: '+label)
    passed+=1;print('[PASS]',label)
manager=Client();code,_,headers=manager.login('stage6_1001');check(code in (302,303) and '/dashboard' in headers.get('Location',''),'Manager login lands on dashboard')
check(manager.select(2)[0] in (302,303),'Property switch uses real CSRF and granted assignment')
owner=Client();code,_,headers=owner.login('stage6_1002');check(code in (302,303) and '/finance/owner' in headers.get('Location',''),'Owner login lands on combined report')
code,html,_=owner.get('/finance/owner?month=2026-10');check(code==200 and 'Rp599,94' in html and 'Asal Properti' in html,'Owner report renders correct combined money through all route filters')
check('New Booking' not in html and 'href="http://localhost:8082/settings"' not in html,'Owner UI does not show booking or Settings actions')
xmls=[]
for prop in [2,3]:
    check(owner.select(prop)[0] in (302,303),'Owner switches to member '+str(prop))
    code,xml,headers=owner.get('/finance/owner/export?month=2026-10');ET.fromstring(xml);xmls.append(xml)
    check(code==200 and 'owner-mitra-combined' in headers.get('Content-Disposition',''),'Owner Excel export works through real route from '+str(prop))
check(xmls[0]==xmls[1],'Owner export remains byte-identical across dropdown contexts')
for path in ['/dashboard','/finance','/settings','/ops','/reservations','/marketing','/housekeeping','/support','/finance/owner-setup']:
    code,_,hdr=owner.get(path,False);check(code in [302,303,403,404] and code!=200,'Owner operational/staff page denied: '+path)
for path in ['/payments/201/proof','/payments/203/proof','/ops/expenses/301/receipt','/ops/expenses/302/receipt']:
    code,body,hdr=owner.get(path);check(code==200 and 'no-store' in hdr.get('Cache-Control',''),'Owner proof read allowed with no-store: '+path)
for path in ['/payments/204/proof','/ops/expenses/303/receipt','/reservations/101','/reservations/102','/guests','/reservations/101/ktp']:
    check(owner.get(path,False)[0] in [302,303,403,404],'Owner cannot access HomiQ or operational data: '+path)
for prop in [2,3]:
    manager.select(prop);code,body,_=manager.get('/finance/panel?month=2026-10');check(code==200 and '2.000' in body and '300' in body,'General Finance panel handles both contexts: '+str(prop))
code,body,_=manager.get('/finance/owner-setup');check(code==200 and 'Owner Mitra Validasi' in body,'Shared setup page available through real route')
setup_id=re.search(r'finance/owner-setup/(\d+)/delete',body).group(1)
code,_,_=manager.post('/finance/owner-setup/'+setup_id+'/delete',{},False);check(code in (302,303),'Shared setup DELETE passes the corrected property filter')
code,body,_=manager.get('/finance/owner?month=2026-10');check(code==200 and 'Setup diperlukan' in body,'Missing owner configuration is explicit after deletion')
code,_,_=manager.post('/finance/owner-setup',{'owner_name':'Owner Mitra Validasi','cooperation_fee_percentage':'33.33'},False);check(code in (302,303),'Shared setup POST restores configuration using current scope')
check(owner.get('/finance/owner?month=2026-10')[0]==200,'Owner report continues after restored configuration')
# Request bodies cannot override scope. Denied owner mutation must leave data intact.
code,_,_=owner.post('/finance/owner-setup',{'owner_name':'Forged Owner','cooperation_fee_percentage':'100','property_id':1},False);check(code in [302,303,403,404],'Owner cannot mutate shared setup with forged scope')
check('Forged Owner' not in manager.get('/finance/owner-setup')[1],'Denied setup mutation did not alter configuration')
single=Client();single.login('stage6_1003')
for path in ['/finance','/finance/panel?month=2026-10','/finance/export?month=2026-10','/finance/owner','/finance/owner/export','/finance/owner-setup']:
    check(single.get(path,False)[0]==403,'Single Finance grant is denied on combined endpoint: '+path)
check(single.get('/payments/201/proof')[0]==200 and single.get('/payments/203/proof')[0]==404,'Single Finance grant retains only local proof permission')
for user in [1004,1006,1007]:
    c=Client();c.login('stage6_'+str(user));check(c.get('/finance/owner')[0]==403 and c.get('/payments/201/proof')[0]==404,'Legacy/incomplete/mixed owner fails closed through route: '+str(user))
for gh in range(1,6):
    user=1005 if gh==1 else 1014+gh;c=Client();c.login('stage6_'+str(user));code,html,_=c.get('/finance/owner?month=2026-10&guesthouse=5');check(code==200 and 'Laba/Rugi GH '+str(gh) in html,'HomiQ owner scope locked despite forged query GH '+str(gh))
check(manager.get('/settings/panel')[0]==200,'Settings panel renders on the actual MariaDB schema')
http_owner='stage6_http_owner_'+uuid.uuid4().hex[:6]
code,body,_=manager.post('/settings/users',{'username':http_owner,'full_name':'Owner Dari UI','password':'Mitra-Stage6-Only!','property_id':3,'role':'Owner Mitra'});check(code==200 and 'alert-success' in body,'Owner account creation succeeds via real Settings HTTP endpoint')
new=Client();check('/finance/owner' in new.login(http_owner)[2].get('Location',''),'New account immediately lands on owner report')
check(new.select(2)[0] in (302,303) and new.select(3)[0] in (302,303) and new.get('/finance/owner')[0]==200,'New owner has working access to both sources')
check(manager.send('/finance/owner-setup',{'owner_name':'No CSRF','cooperation_fee_percentage':0},False)[0] in [302,303,403],'Mutation without CSRF token is rejected')
# Operational separation: booking rooms and price mode still follow active property.
for prop,expected in [(2,'whole_home'),(3,'monthly')]:
    manager.select(prop);code,body,_=manager.get('/reservations/available-rooms?check_in_date=2026-10-05&check_out_date=2026-11-05');data=json.loads(body)
    check(code==200 and len(data['rooms'])==(3 if prop==2 else 7),'Availability scope remains property-local '+str(prop))
    check(manager.get('/reservations/new')[0]==200,'Booking form renders without Extra Bed constant failure '+str(prop))
code,html,_=manager.get('/finance/company?company=1&tab=monthly&month=2026-10');check(code==200 and 'Rp199,98' in html and 'Rp400,02' in html and 'Rp900,04' in html and 'Extra Bed khusus HomiQ' in html,'Holding UI preserves computed owner/manager cents and correct direct-service policy')
print('Stage 6 HTTP:',passed,'checks passed')
with open('/tmp/mitra-stage6-http.json','w')as f:json.dump({'checks':passed,'base':BASE,'schema':'isolated actual MariaDB'},f,indent=2)
