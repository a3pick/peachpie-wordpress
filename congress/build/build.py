import re,base64,subprocess,sys,os
S=os.path.join(os.path.dirname(os.path.abspath(__file__)),'..')+'/'
html=open(S+'ophthalmology_congress_2026_schedule.html',encoding='utf-8').read() if not os.path.exists('template.html') else open('template.html',encoding='utf-8').read()
# strip CDN tags / tailwind config script
html=re.sub(r'\s*<script src="https://cdn.tailwindcss.com"></script>','',html)
html=re.sub(r'\s*<link rel="preconnect"[^>]*>','',html)
html=re.sub(r'\s*<link href="https://fonts.googleapis.com[^>]*>','',html)
html=re.sub(r'\s*<script src="https://unpkg.com/@phosphor-icons/web"></script>','',html)
html=re.sub(r'\s*<script>\s*if \(window.tailwind\) tailwind.config = \{.*?\n    </script>','',html,flags=re.S)
html=html.replace('<style>','<style>\n/*INLINE_CSS*/\n',1) if '/*INLINE_CSS*/' not in html else html
open('template.html','w',encoding='utf-8').write(html)
subprocess.check_call(['npx','tailwindcss','-c','tailwind.config.js','-i','in.css','-o','tw.css','--minify'])
used=set(re.findall(r'ph-([a-z0-9-]+)',html))
b64=lambda p:base64.b64encode(open(p,'rb').read()).decode()
css=[]
for w,d,f,fam in [('regular','regular','Phosphor','Phosphor'),('bold','bold','Phosphor-Bold','Phosphor-Bold'),('fill','fill','Phosphor-Fill','Phosphor-Fill'),('duotone','duotone','Phosphor-Duotone','Phosphor-Duotone')]:
    p=f'node_modules/@phosphor-icons/web/src/{d}/'
    t=open(p+'style.css').read()
    t=re.sub(r'@font-face\s*\{.*?\}',f'@font-face{{font-family:"{fam}";src:url(data:font/woff2;base64,{b64(p+f+".woff2")}) format("woff2");font-weight:normal;font-style:normal;font-display:block}}',t,count=1,flags=re.S)
    out=[];
    for blk in re.findall(r'[^{}]+\{[^{}]*\}|@font-face\{[^}]*\}',t,flags=re.S):
        m=re.search(r'\.ph[-a-z]*\.ph-([a-z0-9-]+)(?::before|::before|:after)',blk)
        if m and m.group(1) not in used: continue
        out.append(blk)
    css.append(''.join(out))
def face(fam,path,rng,ur):
    return f'@font-face{{font-family:"{fam}";font-style:normal;font-display:swap;font-weight:{rng};src:url(data:font/woff2;base64,{b64(path)}) format("woff2");unicode-range:{ur}}}'
fonts=face('Inter Variable','node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2','100 900','U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+2000-206F,U+2074,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD')+\
 face('Vazirmatn Variable','node_modules/@fontsource-variable/vazirmatn/files/vazirmatn-arabic-wght-normal.woff2','100 900','U+0600-06FF,U+0750-077F,U+0870-088E,U+0890-0891,U+0898-08E1,U+08E3-08FF,U+200C-200E,U+2010-2011,U+204F,U+2E41,U+FB50-FDFF,U+FE70-FE74,U+FE76-FEFC')
full=fonts+''.join(css)+open('tw.css').read()
final=html.replace('/*INLINE_CSS*/',full.replace('\\','\\\\') if False else full)
open(S+'ophthalmology_congress_2026_schedule.html','w',encoding='utf-8').write(final)
print(len(final)//1024,'KB')
