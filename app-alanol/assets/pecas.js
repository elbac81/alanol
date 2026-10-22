// Peças das bombas de poço (admin/pecas.php).
// Dados por pecas_api.php (data/pecas/); desenhos e fotos servidos por pecas_ficheiro.php.
(function () {
  const $ = (s) => document.querySelector(s);
  const E = (tag, attrs, ...kids) => {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
      if (v == null || v === false) continue;
      if (k === 'class') el.className = v;
      else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
      else if (k === 'text') el.textContent = v;
      else el.setAttribute(k, v === true ? '' : v);
    }
    for (const k of kids.flat()) if (k != null && k !== false) el.append(k.nodeType ? k : String(k));
    return el;
  };
  const norm = (s) => String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

  // Estado
  const st = {
    vista: 'pecas', pecas: new Map(), modelos: new Map(), sel: null,
    q: '', familia: '', semFoto: false, semDesenho: false,
    podeEscrever: true, ligado: false, versao: null, carregado: { pecas: false, modelos: false },
    enviar: {}, // uploads em curso por ficha
  };
  try { const g = JSON.parse(localStorage.getItem('pecas-alanol') || '{}'); if (g.vista) st.vista = g.vista; } catch (e) {}
  const lembrar = () => { try { localStorage.setItem('pecas-alanol', JSON.stringify({ vista: st.vista })); } catch (e) {} };

  const blob = (id) => PA.ficheiro + '?f=' + encodeURIComponent(id);
  const media = (d, campo) => Array.isArray(d && d[campo]) ? d[campo] : [];
  const pdf = (m) => (m.tipo || '').includes('pdf');
  const primeiraImg = (d) => [...media(d, 'fotos'), ...media(d, 'desenhos')].find((m) => !pdf(m));
  const tituloPeca = (p) => (p.titulo && p.titulo.trim()) || p.descricao || p.codigo;

  // Onde é usada cada peça
  function usos(cod) {
    const r = [];
    for (const m of st.modelos.values()) for (const x of (m.pecas || [])) if (String(x.codigo) === String(cod)) r.push({ m, sq: x.sq });
    return r.sort((a, b) => ordemModelo(a.m) - ordemModelo(b.m));
  }
  const ordemModelo = (m) => (m.ordem != null ? m.ordem : 999);

  // ---------- Lista ----------
  function filtrar() {
    const q = norm(st.q).trim();
    if (st.vista === 'pecas') {
      let fams = null;
      if (st.familia) { fams = new Set(); for (const m of st.modelos.values()) if (m.familia === st.familia) for (const x of m.pecas || []) fams.add(String(x.codigo)); }
      return [...st.pecas.values()].filter((p) => {
        if (fams && !fams.has(String(p.codigo))) return false;
        if (st.semFoto && media(p, 'fotos').length) return false;
        if (st.semDesenho && media(p, 'desenhos').length) return false;
        if (!q) return true;
        return norm([p.codigo, p.descricao, p.titulo, p.referencia, p.notas].join(' ')).includes(q);
      }).sort((a, b) => String(a.codigo).localeCompare(String(b.codigo)));
    }
    return [...st.modelos.values()].filter((m) => {
      if (st.familia && m.familia !== st.familia) return false;
      if (st.semFoto && media(m, 'fotos').length) return false;
      if (st.semDesenho && media(m, 'desenhos').length) return false;
      if (!q) return true;
      return norm([m.nome, m.codigo, m.familia, m.titulo, m.referencia, m.notas].join(' ')).includes(q);
    }).sort((a, b) => ordemModelo(a) - ordemModelo(b));
  }

  function marcasQ(d) {
    const nd = media(d, 'desenhos').length, nf = media(d, 'fotos').length;
    return E('span', { class: 'marcas' },
      E('span', { class: 'marca-q' + (nd ? ' marca-q--tem' : ''), title: nd + ' desenho(s)' }, 'D ' + nd),
      E('span', { class: 'marca-q' + (nf ? ' marca-q--tem' : ''), title: nf + ' foto(s)' }, 'F ' + nf));
  }
  // Quadrado na lista onde se larga (ou escolhe) UM desenho ou UMA foto; mais do que um só na ficha
  function zona(d, tipo, campo) {
    const lista = media(d, campo), m = lista[0];
    const chave = tipo + ':' + d.codigo + ':' + campo;
    const rot = campo === 'fotos' ? 'Foto' : 'Desenho';
    const pode = !!st.podeEscrever;
    const em = st.enviar[chave] || 0;
    let dentro;
    if (em) dentro = E('span', { class: 'zona__txt', text: 'A enviar…' });
    else if (m) dentro = pdf(m) ? E('span', { class: 'zona__pdf', text: 'PDF' }) : E('img', { src: blob(m.id), alt: '', loading: 'lazy' });
    else dentro = E('span', { class: 'zona__mais', text: pode ? '+' : '—' });
    const ajuda = m ? `${rot}: ${lista.length} ficheiro${lista.length === 1 ? '' : 's'}. Para juntar mais, abre a ficha.`
      : pode ? `Larga aqui ${campo === 'fotos' ? 'uma foto' : 'um desenho'}, ou toca para escolher` : `Sem ${rot.toLowerCase()}`;
    const z = E('div', { class: 'zona' + (m ? ' zona--tem' : ''), role: 'button', tabindex: '0', title: ajuda, 'aria-label': ajuda },
      dentro, E('span', { class: 'zona__rot', text: rot + (lista.length > 1 ? ' +' + (lista.length - 1) : '') }));
    const acao = () => {
      if (m || !pode) { abrir(tipo, d.codigo); return; }
      escolherPara(tipo, d.codigo, campo);
    };
    z.addEventListener('click', acao);
    z.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); acao(); } });
    if (pode) {
      z.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = m ? 'none' : 'copy';
        z.classList.add(m ? 'zona--nao' : 'zona--sobre');
      });
      z.addEventListener('dragleave', () => z.classList.remove('zona--sobre', 'zona--nao'));
      z.addEventListener('drop', (e) => {
        e.preventDefault(); z.classList.remove('zona--sobre', 'zona--nao');
        if (m) { avisar(`Já tem ${campo === 'fotos' ? 'foto' : 'desenho'}. Para juntar mais, abre a ficha.`); return; }
        const fs = [...(e.dataTransfer.files || [])];
        if (!fs.length) return;
        if (fs.length > 1) avisar('Na lista entra só um ficheiro. Ficou o primeiro; os outros juntam-se na ficha.');
        const f = fs[0], t = tipoDe(f);
        if (!t || (campo === 'fotos' && t === 'application/pdf')) { avisar(campo === 'fotos' ? 'A foto tem de ser uma imagem.' : 'O desenho tem de ser uma imagem ou um PDF.'); return; }
        enviar({ tipo, id: String(d.codigo), campo, lista: true }, [f]);
      });
    }
    return z;
  }
  let tAviso = null;
  function avisar(txt) {
    const t = $('#toast'); t.textContent = txt; t.hidden = false;
    clearTimeout(tAviso); tAviso = setTimeout(() => { t.hidden = true; }, 4000);
  }
  // Largar ficheiros fora de um quadrado não abre o ficheiro no browser
  document.addEventListener('dragover', (e) => e.preventDefault());
  document.addEventListener('drop', (e) => e.preventDefault());

  function desenharLista() {
    const caixa = $('#lista');
    caixa.replaceChildren();
    const ok = st.carregado.pecas && st.carregado.modelos;
    const itens = filtrar();
    const total = st.vista === 'pecas' ? st.pecas.size : st.modelos.size;
    $('#contagem').textContent = ok ? `${itens.length} de ${total} ${st.vista === 'pecas' ? 'peças' : 'modelos'}` : '';
    if (st.dbFalhou && !ok) {
      caixa.append(E('div', { class: 'vazio' }, E('strong', { text: 'Sem ligação ao servidor' }), st.dbFalhou));
      return;
    }
    if (!ok) { caixa.append(E('div', { class: 'vazio' }, E('strong', { text: 'A carregar…' }), 'Peças e modelos das bombas de poço.')); return; }
    if (!total) { caixa.append(E('div', { class: 'vazio' }, E('strong', { text: 'A base ainda está vazia' }), 'Carrega em «Importar listagem…» e escolhe o ficheiro alanol-listagem.json.')); return; }
    if (!itens.length) { caixa.append(E('div', { class: 'vazio' }, E('strong', { text: 'Nada encontrado' }), 'Muda a procura ou tira os filtros.')); return; }

    const ul = E('ul', { class: 'lista' });
    if (st.vista === 'pecas') {
      for (const p of itens) {
        const n = usos(p.codigo).length;
        ul.append(E('li', { class: 'linha', 'aria-current': st.sel && st.sel.tipo === 'peca' && st.sel.id === String(p.codigo) ? 'true' : null },
          E('button', { class: 'item', onclick: () => abrir('peca', p.codigo) },
            E('span', { class: 'item__txt' },
              E('div', { class: 'item__cod', text: p.codigo + (p.referencia ? '  ·  ref. ' + p.referencia : '') }),
              E('div', { class: 'item__tit', text: tituloPeca(p) }),
              E('div', { class: 'item__sub', text: `Em ${n} modelo${n === 1 ? '' : 's'}` }))),
          zona(p, 'peca', 'desenhos'), zona(p, 'peca', 'fotos')));
      }
    } else {
      let fam = null;
      for (const m of itens) {
        if (m.familia !== fam) { fam = m.familia; ul.append(E('li', { class: 'familia', text: 'Família ' + fam })); }
        ul.append(E('li', { class: 'linha', 'aria-current': st.sel && st.sel.tipo === 'modelo' && st.sel.id === String(m.codigo) ? 'true' : null },
          E('button', { class: 'item', onclick: () => abrir('modelo', m.codigo) },
            E('span', { class: 'item__txt' },
              E('div', { class: 'item__cod', text: m.codigo }),
              E('div', { class: 'item__tit', text: m.nome }),
              E('div', { class: 'item__sub', text: [m.especificacoes && m.especificacoes['Voltagem (V)'], (m.pecas || []).length + ' peças'].filter(Boolean).join(' · ') }))),
          zona(m, 'modelo', 'desenhos'), zona(m, 'modelo', 'fotos')));
      }
    }
    caixa.append(ul);
  }

  function desenharFamilias() {
    const sel = $('#f-familia'), atual = st.familia;
    const fams = [...new Set([...st.modelos.values()].sort((a, b) => ordemModelo(a) - ordemModelo(b)).map((m) => m.familia))];
    sel.replaceChildren(E('option', { value: '' }, 'Todas as famílias'), ...fams.map((f) => E('option', { value: f, selected: f === atual }, f)));
  }

  // ---------- Ficha ----------
  const rascunho = {}; // campos por gravar: chave "tipo:id"
  function atual() {
    if (!st.sel) return null;
    return st.sel.tipo === 'peca' ? st.pecas.get(st.sel.id) : st.modelos.get(st.sel.id);
  }

  function abrir(tipo, id) {
    st.sel = { tipo, id: String(id) };
    document.body.classList.add('com-ficha');
    desenharLista(); desenharFicha();
    if (window.matchMedia('(max-width: 820px)').matches) window.scrollTo(0, 0);
    else $('#ficha').scrollIntoView({ block: 'nearest' });
  }
  function fechar() { st.sel = null; document.body.classList.remove('com-ficha'); desenharLista(); desenharFicha(); }

  function desenharFicha() {
    const caixa = $('#ficha');
    // guardar o foco e o que se está a escrever para não perder ao redesenhar
    const foco = document.activeElement && caixa.contains(document.activeElement) ? document.activeElement.id : null;
    const d = atual();
    if (!d) {
      caixa.replaceChildren(E('div', { class: 'vazio' }, E('strong', { text: st.vista === 'pecas' ? 'Escolhe uma peça' : 'Escolhe um modelo' }),
        'Cada ficha guarda título, referência, notas e quantos desenhos e fotos quiseres.'));
      return;
    }
    const chave = st.sel.tipo + ':' + st.sel.id;
    const r = rascunho[chave] || {};
    const val = (c) => (c in r ? r[c] : (d[c] || ''));
    const ro = !st.podeEscrever;

    const cab = E('div', { class: 'ficha__cab' },
      E('button', { class: 'btn voltar', onclick: fechar }, '‹ Voltar à lista'));
    if (st.sel.tipo === 'peca') {
      cab.append(E('div', { class: 'ficha__cod', text: 'Código ' + d.codigo + (d.referencia ? '  ·  Ref. ' + d.referencia : '') }),
        E('h2', { class: 'ficha__tit', text: tituloPeca(d) }),
        E('div', { class: 'ficha__orig' }, 'Descrição na listagem: ', E('b', { text: d.descricao || '—' })));
    } else {
      cab.append(E('div', { class: 'ficha__cod', text: 'Código ' + d.codigo + '  ·  Família ' + d.familia }),
        E('h2', { class: 'ficha__tit', text: (d.titulo && d.titulo.trim()) || d.nome }),
        ...(d.titulo ? [E('div', { class: 'ficha__orig' }, 'Modelo: ', E('b', { text: d.nome }))] : []));
    }

    const inp = (id, campo, rot, tipo) => {
      const attrs = { id, readonly: ro, oninput: (e) => { (rascunho[chave] = rascunho[chave] || {})[campo] = e.target.value; marcarSujo(); } };
      const el = tipo === 'area' ? E('textarea', attrs) : E('input', Object.assign({ type: 'text' }, attrs));
      el.value = val(campo);
      return E('div', { class: 'campo' + (tipo === 'area' ? ' campo--largo' : '') }, E('label', { for: id, text: rot }), el);
    };
    const btGuardar = E('button', { class: 'btn btn--pri', id: 'guardar', disabled: true, onclick: guardar }, 'Guardar');
    const msg = E('span', { class: 'aviso', id: 'msg-guardar' });
    const campos = E('div', { class: 'bloco' },
      E('div', { class: 'bloco__tit' }, E('h3', { text: 'Ficha' })),
      E('div', { class: 'campos' },
        inp('c-titulo', 'titulo', 'Título'),
        inp('c-referencia', 'referencia', 'Referência'),
        inp('c-notas', 'notas', 'Notas', 'area')),
      ro ? E('div', { class: 'aviso', text: 'Só leitura: não tens permissão para mudar a base de dados.' }) : E('div', { class: 'guardar' }, btGuardar, msg));

    const partes = [cab, campos,
      blocoMedia(d, 'desenhos', 'Desenhos', 'image/*,application/pdf,.pdf'),
      blocoMedia(d, 'fotos', 'Fotos', 'image/*')];

    if (st.sel.tipo === 'peca') {
      const u = usos(d.codigo);
      partes.push(E('div', { class: 'bloco' },
        E('div', { class: 'bloco__tit' }, E('h3', { text: `Usada em ${u.length} modelo${u.length === 1 ? '' : 's'}` })),
        u.length ? E('div', { class: 'usada' }, u.map(({ m, sq }) => E('button', { onclick: () => { trocarVista('modelos', false); abrir('modelo', m.codigo); } },
          m.nome, ' ', E('span', { class: 'sq', text: 'n.º ' + sq })))) : E('div', { class: 'aviso', text: 'Não aparece em nenhum modelo.' })));
    } else {
      const esp = d.especificacoes || {};
      const ks = Object.keys(esp);
      if (ks.length) partes.push(E('div', { class: 'bloco' }, E('div', { class: 'bloco__tit' }, E('h3', { text: 'Características' })),
        E('dl', { class: 'esp' }, ks.map((k) => E('div', {}, E('dt', { text: k }), E('dd', { text: esp[k] }))))));
      const lin = (d.pecas || []).map((x) => {
        const p = st.pecas.get(String(x.codigo)) || { codigo: x.codigo, descricao: '?' };
        const nd = media(p, 'desenhos').length, nf = media(p, 'fotos').length;
        return E('tr', { class: 'clicavel', tabindex: '0', onclick: () => { trocarVista('pecas', false); abrir('peca', x.codigo); },
          onkeydown: (e) => { if (e.key === 'Enter') { trocarVista('pecas', false); abrir('peca', x.codigo); } } },
          E('td', { class: 'num', text: x.sq }), E('td', { class: 'num', text: x.codigo }), E('td', { text: tituloPeca(p) }),
          E('td', { text: p.referencia || '' }), E('td', {}, marcasQ(p)));
      });
      partes.push(E('div', { class: 'bloco' }, E('div', { class: 'bloco__tit' }, E('h3', { text: `Peças (${lin.length})` })),
        E('div', { class: 'tabela-wrap' }, E('table', {},
          E('thead', {}, E('tr', {}, E('th', { text: 'N.º' }), E('th', { text: 'Código' }), E('th', { text: 'Peça' }), E('th', { text: 'Ref.' }), E('th', { text: 'Ficheiros' }))),
          E('tbody', {}, lin)))));
    }
    caixa.replaceChildren(E('article', { class: 'ficha' }, partes));
    marcarSujo();
    if (foco && document.getElementById(foco)) document.getElementById(foco).focus();
  }

  function sujo() {
    const d = atual(); if (!d) return false;
    const r = rascunho[st.sel.tipo + ':' + st.sel.id] || {};
    return Object.keys(r).some((k) => (r[k] || '') !== (d[k] || ''));
  }
  function marcarSujo() { const b = $('#guardar'); if (b) b.disabled = !sujo(); }

  // Pedido ao servidor; rejeita com {erro}
  async function api(acao, corpo) {
    const op = corpo === undefined ? {} : { method: 'POST', headers: { 'X-Token': PA.token }, body: corpo instanceof FormData ? corpo : JSON.stringify(corpo) };
    let r;
    try { r = await fetch(PA.api + '?acao=' + acao, Object.assign({ credentials: 'same-origin' }, op)); }
    catch (e) { throw { erro: 'Sem ligação ao servidor. Verifica a internet.' }; }
    let j = null; try { j = await r.json(); } catch (e) {}
    if (r.status === 401) { location.reload(); throw { erro: 'Sessão terminada.' }; }
    if (!r.ok || !j) throw { erro: (j && j.erro) || 'Erro no servidor (' + r.status + ').' };
    return j;
  }
  const mapaDe = (tipo) => (tipo === 'peca' ? st.pecas : st.modelos);
  function aplicar(tipo, doc) {
    if (doc && doc.codigo != null) mapaDe(tipo).set(String(doc.codigo), doc);
  }

  async function guardar() {
    const d = atual(); if (!d) return;
    const sel = st.sel, chave = sel.tipo + ':' + sel.id;
    const r = Object.assign({}, rascunho[chave] || {});
    const msg = $('#msg-guardar'); const b = $('#guardar');
    b.disabled = true; msg.className = 'aviso'; msg.textContent = 'A guardar…';
    try {
      const res = await api('guardar', { tipo: sel.tipo, id: sel.id, campos: r });
      delete rascunho[chave];
      aplicar(sel.tipo, res.doc); desenharLista(); desenharFicha();
      const m2 = $('#msg-guardar'); if (m2) { m2.className = 'aviso aviso--ok'; m2.textContent = 'Guardado'; }
    } catch (e) {
      const m2 = $('#msg-guardar') || msg; m2.className = 'aviso aviso--erro'; m2.textContent = e.erro || 'Não foi possível guardar.';
      const b2 = $('#guardar'); if (b2) b2.disabled = false;
    }
  }

  // ---------- Desenhos e fotos ----------
  const confirmar = {}; // id do ficheiro à espera de confirmação
  function blocoMedia(d, campo, rot, aceita) {
    const lista = media(d, campo);
    const chave = st.sel.tipo + ':' + st.sel.id + ':' + campo;
    const g = E('div', { class: 'galeria' });
    lista.forEach((m, i) => {
      const card = E('div', { class: 'media' + (pdf(m) ? ' media--pdf' : '') });
      if (pdf(m)) card.append(E('a', { href: blob(m.id), target: '_blank', rel: 'noopener' }, E('span', { class: 'pdf-tag', text: 'PDF' }), m.nome || 'Abrir'));
      else card.append(E('button', { class: 'abrir', 'aria-label': 'Ver ' + (m.nome || rot), onclick: () => abrirVisor(lista.filter((x) => !pdf(x)), m.id) },
        E('img', { src: blob(m.id), alt: m.nome || rot, loading: 'lazy' })));
      if (st.podeEscrever) {
        if (confirmar[chave] === m.id) {
          card.append(E('div', { class: 'media__conf' }, 'Tirar?',
            E('button', { class: 'sim', onclick: () => tirar(campo, m) }, 'Sim'),
            E('button', { onclick: () => { delete confirmar[chave]; desenharFicha(); } }, 'Não')));
        } else card.append(E('button', { class: 'media__tirar', 'aria-label': 'Tirar ' + (m.nome || ''), onclick: () => { confirmar[chave] = m.id; desenharFicha(); } }, 'Tirar'));
      }
      g.append(card);
    });
    const emCurso = st.enviar[chave] || 0;
    for (let i = 0; i < emCurso; i++) g.append(E('div', { class: 'a-enviar', text: 'A enviar…' }));
    if (st.podeEscrever) {
      g.append(E('button', { class: 'add', onclick: () => escolher(campo, aceita) }, E('span', { text: '＋' }), campo === 'fotos' ? 'Juntar fotos' : 'Juntar desenhos'));
    }
    const nota = campo === 'desenhos' ? E('div', { class: 'aviso', text: 'Imagem ou PDF, até 20 MB cada.' }) : null;
    const err = st.erroMedia && st.erroMedia.chave === chave ? E('div', { class: 'aviso aviso--erro', text: st.erroMedia.texto }) : null;
    return E('div', { class: 'bloco' },
      E('div', { class: 'bloco__tit' }, E('h3', { text: `${rot} (${lista.length})` })),
      g, nota, err);
  }

  let alvo = null;
  function escolher(campo, aceita) {
    alvo = { tipo: st.sel.tipo, id: st.sel.id, campo };
    const f = $('#ficheiro'); f.accept = aceita; f.multiple = true; f.value = ''; f.click();
  }
  function escolherPara(tipo, id, campo) {
    alvo = { tipo, id: String(id), campo, lista: true };
    const f = $('#ficheiro'); f.accept = campo === 'fotos' ? 'image/*' : 'image/*,application/pdf,.pdf'; f.multiple = false; f.value = ''; f.click();
  }
  $('#ficheiro').addEventListener('change', (e) => {
    const files = [...e.target.files]; if (!files.length || !alvo) return;
    const a = alvo; alvo = null;
    enviar(a, a.lista ? files.slice(0, 1) : files);
  });
  async function enviar(a, files) {
    const chave = a.tipo + ':' + a.id + ':' + a.campo;
    st.erroMedia = null;
    st.enviar[chave] = (st.enviar[chave] || 0) + files.length; desenharLista(); desenharFicha();
    const falhas = [];
    for (const f of files) {
      try {
        const corpo = a.campo === 'fotos' ? await reduzir(f) : f;
        const fd = new FormData();
        fd.append('tipo', a.tipo); fd.append('id', a.id); fd.append('campo', a.campo);
        if (a.lista) fd.append('so_se_vazio', '1');
        fd.append('ficheiro', corpo, corpo === f ? f.name : f.name.replace(/\.[^.]+$/, '') + '.jpg');
        const res = await api('juntar', fd);
        aplicar(a.tipo, res.doc);
      } catch (err) {
        falhas.push((files.length > 1 ? f.name + ': ' : '') + (err.erro || 'não foi possível enviar'));
      }
      st.enviar[chave]--;
      desenharLista(); desenharFicha();
    }
    if (falhas.length) { st.erroMedia = { chave, texto: falhas.join(' · ') }; avisar(falhas.join(' · ')); }
    else if (a.lista) avisar((a.campo === 'fotos' ? 'Foto' : 'Desenho') + ' guardado.');
    desenharLista(); desenharFicha();
  }
  function tipoDe(f) {
    if (f.type && /^(image\/(png|jpeg|gif|webp)|application\/pdf)$/.test(f.type)) return f.type;
    const ext = (f.name.split('.').pop() || '').toLowerCase();
    return { pdf: 'application/pdf', png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif', webp: 'image/webp' }[ext] || '';
  }
  // Fotos grandes encolhem para 2400 px antes de enviar
  function reduzir(f) {
    return new Promise((res) => {
      if (!/^image\/(jpeg|png|webp)$/.test(tipoDe(f)) || f.size < 1.5e6) return res(f);
      const url = URL.createObjectURL(f); const img = new Image();
      img.onload = () => {
        const max = 2400, k = Math.min(1, max / Math.max(img.width, img.height));
        const c = document.createElement('canvas'); c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(url);
        c.toBlob((b) => res(b && b.size < f.size ? b : f), 'image/jpeg', 0.85);
      };
      img.onerror = () => { URL.revokeObjectURL(url); res(f); };
      img.src = url;
    });
  }
  async function tirar(campo, m) {
    const sel = st.sel, chave = sel.tipo + ':' + sel.id + ':' + campo;
    delete confirmar[chave];
    try {
      const res = await api('tirar', { tipo: sel.tipo, id: sel.id, campo, fid: m.id });
      aplicar(sel.tipo, res.doc);
    } catch (e) { st.erroMedia = { chave, texto: e.erro || 'Não foi possível tirar o ficheiro.' }; }
    desenharLista(); desenharFicha();
  }

  // ---------- Visor ----------
  let visor = { lista: [], i: 0 };
  function abrirVisor(lista, id) { visor = { lista, i: Math.max(0, lista.findIndex((x) => x.id === id)) }; mostrarVisor(); $('#visor').hidden = false; $('#visor-fechar').focus(); }
  function mostrarVisor() {
    const m = visor.lista[visor.i]; if (!m) return;
    $('#visor-img').src = blob(m.id); $('#visor-img').alt = m.nome || '';
    $('#visor-nome').textContent = m.nome || ''; $('#visor-pos').textContent = (visor.i + 1) + ' / ' + visor.lista.length;
    $('#visor-ant').disabled = $('#visor-seg').disabled = visor.lista.length < 2;
  }
  const passar = (n) => { visor.i = (visor.i + n + visor.lista.length) % visor.lista.length; mostrarVisor(); };
  $('#visor-fechar').onclick = () => { $('#visor').hidden = true; };
  $('#visor-ant').onclick = () => passar(-1); $('#visor-seg').onclick = () => passar(1);
  document.addEventListener('keydown', (e) => {
    if ($('#visor').hidden) return;
    if (e.key === 'Escape') $('#visor').hidden = true; else if (e.key === 'ArrowLeft') passar(-1); else if (e.key === 'ArrowRight') passar(1);
  });

  // ---------- Separadores e filtros ----------
  function trocarVista(v, limpar = true) {
    st.vista = v; lembrar();
    $('#tab-pecas').setAttribute('aria-selected', v === 'pecas'); $('#tab-modelos').setAttribute('aria-selected', v === 'modelos');
    if (limpar) { st.sel = null; document.body.classList.remove('com-ficha'); }
    desenharLista(); desenharFicha();
  }
  $('#tab-pecas').onclick = () => trocarVista('pecas');
  $('#tab-modelos').onclick = () => trocarVista('modelos');
  $('#procura').addEventListener('input', (e) => { st.q = e.target.value; desenharLista(); });
  $('#f-familia').addEventListener('change', (e) => { st.familia = e.target.value; desenharLista(); });
  for (const [id, k] of [['#f-sem-foto', 'semFoto'], ['#f-sem-desenho', 'semDesenho']]) {
    $(id).onclick = () => { st[k] = !st[k]; $(id).setAttribute('aria-pressed', st[k]); desenharLista(); };
  }

  // ---------- Exportar ----------
  const csvCel = (v) => { const s = String(v == null ? '' : v); return /[";\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; };
  $('#m-exportar').onclick = () => {
    fecharMenu();
    const linhas = [['Código', 'Descrição', 'Título', 'Referência', 'Notas', 'Desenhos', 'Fotos', 'Modelos (n.º)']];
    for (const p of [...st.pecas.values()].sort((a, b) => String(a.codigo).localeCompare(String(b.codigo)))) {
      linhas.push([p.codigo, p.descricao, p.titulo, p.referencia, p.notas, media(p, 'desenhos').length, media(p, 'fotos').length,
        usos(p.codigo).map((u) => u.m.nome + ' (' + u.sq + ')').join(', ')]);
    }
    const txt = '\ufeff' + linhas.map((l) => l.map(csvCel).join(';')).join('\r\n');
    const url = URL.createObjectURL(new Blob([txt], { type: 'text/csv;charset=utf-8' }));
    const a = E('a', { href: url, download: 'pecas-alanol.csv' }); document.body.append(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
  };

  // ---------- Menu: importar, contas, password ----------
  const fecharMenu = () => {};
  const jan = $('#janela');
  function abrirJanela(titulo, ...corpo) {
    jan.replaceChildren(E('h2', { text: titulo }), ...corpo);
    if (!jan.open) jan.showModal();
  }
  const fecharJanela = () => jan.close();
  const botoes = (...b) => E('div', { class: 'botoes' }, E('button', { type: 'button', class: 'btn', onclick: fecharJanela }, 'Fechar'), ...b);

  if ($('#m-importar')) $('#m-importar').onclick = () => {
    fecharMenu();
    const msg = E('p', { class: 'aviso' });
    const inp = E('input', { type: 'file', id: 'imp-ficheiro', accept: '.json,application/json', required: true });
    abrirJanela('Importar listagem',
      E('form', { onsubmit: async (e) => {
        e.preventDefault();
        if (!inp.files[0]) return;
        const fd = new FormData(); fd.append('ficheiro', inp.files[0]);
        msg.className = 'aviso'; msg.textContent = 'A importar…';
        try {
          const r = await api('importar', fd);
          msg.className = 'aviso aviso--ok';
          msg.textContent = `Entraram ${r.novos.pecas} peças e ${r.novos.modelos} modelos novos. O que já existia ficou como estava.`;
          await carregar(true);
        } catch (err) { msg.className = 'aviso aviso--erro'; msg.textContent = err.erro; }
      } },
        E('p', { class: 'aviso', text: 'Escolhe o ficheiro da listagem (alanol-listagem.json). Só entram peças e modelos que ainda não existem.' }),
        E('div', { class: 'campo' }, E('label', { for: 'imp-ficheiro', text: 'Ficheiro' }), inp),
        msg, botoes(E('button', { class: 'btn btn--pri', type: 'submit' }, 'Importar'))));
  };

  // ---------- Arranque e atualização ----------
  async function carregar(forcar) {
    try {
      if (!forcar && st.versao !== null) {
        const v = await api('versao');
        if (v.versao === st.versao) return;
      }
      const r = await api('estado');
      st.versao = r.versao;
      st.pecas.clear(); for (const [id, d] of Object.entries(r.pecas || {})) st.pecas.set(id, Object.assign({ codigo: id }, d));
      st.modelos.clear(); for (const [id, d] of Object.entries(r.modelos || {})) st.modelos.set(id, Object.assign({ codigo: id }, d));
      st.carregado.pecas = st.carregado.modelos = true; st.dbFalhou = null;
      $('#estado-db').textContent = '';
      desenharFamilias(); desenharLista();
      // não redesenhar a ficha enquanto se escreve nela
      const ativo = document.activeElement;
      if (!(ativo && $('#ficha').contains(ativo) && /INPUT|TEXTAREA/.test(ativo.tagName))) desenharFicha();
    } catch (e) {
      st.dbFalhou = e.erro || 'Erro'; $('#estado-db').textContent = 'Sem ligação'; desenharLista();
    }
  }
  trocarVista(st.vista);
  carregar(true);
  setInterval(() => { if (!document.hidden) carregar(false); }, 20000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) carregar(false); });
})();
