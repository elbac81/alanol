#!/usr/bin/env python3
"""Peças Alanol — converte a listagem de peças em Excel (uma folha por família) no ficheiro
que a app importa (Menu ☰ › Importar listagem…).

Uso:  python3 ferramentas/excel_para_json.py "Poço - listagem de peças.xlsx" alanol-listagem.json
Precisa de: pip install openpyxl

Cada folha tem blocos «SQ | CÓDIGO | DESCRIÇÃO» (um por modelo) e uma tabela de características
(linha «MODELO», linha «CÓDIGO» e as linhas de dados). A foto da folha passa a foto dos modelos.
Blocos sem título levam o nome do modelo pela ordem da tabela.
"""
import openpyxl,sys,re,json,base64
wb=openpyxl.load_workbook(sys.argv[1])
imagens={}
out={'modelos':[], 'pecas':{}, 'avisos':[]}
def norm(s): return re.sub(r'\s+',' ',str(s)).strip()
for ws in wb:
  rows=list(ws.iter_rows())
  # find header rows
  hdrs=[]
  for r in rows:
    for c in r:
      if c.value=='SQ': hdrs.append((c.row,c.column))
  # spec table: MODELO row
  spec={}; models=[]
  for r in rows:
    for c in r:
      if c.value=='MODELO':
        mr=c.row; 
        cols=[(cc.column,norm(cc.value)) for cc in r if cc.column>c.column+1 and cc.value]
        models=cols
        # spec rows below until ~row mr+22
        for rr in rows[mr:mr+22]:
          lab=None
          for cc in rr:
            if cc.column in (c.column,c.column+1) and isinstance(cc.value,str) and cc.value.strip(): lab=norm(cc.value)
          if not lab: continue
          vals={}
          for col,_ in models:
            v=ws.cell(rr[0].row,col).value
            vals[col]=v
          spec[lab]=vals
        break
    if models: break
  # merged fill for spec rows where only first col has value (merged across)
  merged=ws.merged_cells.ranges
  blocks=[]
  for i,(hr,hc) in enumerate(hdrs):
    # title: look up to 2 rows above at columns hc..hc+2
    title=None
    for up in (1,2):
      if hr-up<1: break
      for cc in range(max(1,hc-1),hc+3):
        v=ws.cell(hr-up,cc).value
        if isinstance(v,str) and re.search(r'\d',v) and 'PROPER' not in v: title=norm(v)
      if title: break
    parts=[]
    r=hr+1
    while True:
      sq=ws.cell(r,hc).value; cod=ws.cell(r,hc+1).value; d=ws.cell(r,hc+2).value
      if sq is None and cod is None: break
      if cod is None or d is None: out['avisos'].append(f'{ws.title} linha {r}: incompleta'); r+=1; continue
      parts.append({'sq':sq,'codigo':str(cod).strip(),'descricao':norm(d)}); r+=1
    blocks.append((title,parts))
  for im in getattr(ws,'_images',[]):
    imagens.setdefault(ws.title, base64.b64encode(im._data()).decode())
  print(ws.title,'| modelos:',[m for _,m in models],'| blocos:',[(t,len(p)) for t,p in blocks])
  for idx,(title,parts) in enumerate(blocks):
    mcol=None
    if title:
      key=re.sub(r'\s','',title).upper()
      for col,m in models:
        if re.sub(r'\s','',m).upper()==key: mcol=(col,m)
    if not mcol and idx<len(models): mcol=models[idx]; 
    nome=title or (mcol[1] if mcol else f'{ws.title} #{idx+1}')
    esp={}
    if mcol:
      first=models[0][0]
      for lab,vals in spec.items():
        v=vals.get(mcol[0])
        if v is None:
          # merged across? take first col value if a merged range covers it
          v0=vals.get(first)
          if v0 is not None and lab not in ('CÓDIGO',): 
            v=v0
        if v is not None: esp[lab]=norm(v)
    cod=esp.pop('CÓDIGO',None)
    for k in list(esp):
      if k.isupper(): esp.pop(k)
    out['modelos'].append({'nome':re.sub(r'(\d)\s*HP',r'\1 HP',nome).replace('  ',' '),'nome_tabela':mcol[1] if mcol else None,'familia':ws.title,'codigo':str(cod) if cod else None,'especificacoes':esp,
      'pecas':[{'sq':p['sq'],'codigo':p['codigo']} for p in parts],
      'fotos':[{'imagem':ws.title,'nome':'Foto da listagem ('+ws.title+')'}] if getattr(ws,'_images',None) else []})
    for p in parts:
      e=out['pecas'].setdefault(p['codigo'],{'codigo':p['codigo'],'descricao':p['descricao'],'variantes':set()})
      e['variantes'].add(p['descricao'].upper())
for k,e in out['pecas'].items():
  if len(e['variantes'])>1: out['avisos'].append(f'Peça {k} com descrições diferentes: {sorted(e["variantes"])}')
  del e['variantes']
out['pecas']=sorted(out['pecas'].values(),key=lambda x:x['codigo'])
saida={'origem':sys.argv[1].split('/')[-1],'imagens':imagens,
  'pecas':[{'codigo':p['codigo'],'descricao':p['descricao'],'desenhos':[],'fotos':[]} for p in out['pecas']],
  'modelos':[{'codigo':m['codigo'],'nome':m['nome'],'familia':m['familia'],'ordem':i,'especificacoes':m['especificacoes'],'pecas':m['pecas'],'desenhos':[],'fotos':m['fotos']} for i,m in enumerate(out['modelos'])]}
json.dump(saida,open(sys.argv[2],'w'),ensure_ascii=False)
print('modelos',len(saida['modelos']),'pecas',len(saida['pecas']),'imagens',len(imagens))
for a in out['avisos']: print('AVISO',a)
