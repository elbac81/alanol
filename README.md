# Peças Alanol

App das peças das bombas de poço da Alanol: cada peça e cada modelo têm título, referência,
notas, desenhos (imagem ou PDF) e fotos. Na lista larga-se **um** desenho e **uma** foto por
peça; mais do que um junta-se na ficha da peça.

PHP simples, sem base de dados nem bibliotecas: os dados ficam em `dados/` (JSON), que vive só
no servidor e nunca vai para o Git.

## Ficheiros

| Ficheiro | O que faz |
|---|---|
| `index.php` | Entrada (login; na 1.ª vez cria a conta de administrador) e a app |
| `api.php` | Leituras e gravações (`estado`, `guardar`, `juntar`, `tirar`, `importar`, contas) |
| `ficheiro.php` | Serve desenhos e fotos, só a quem tem sessão |
| `_lib.php` | Dados JSON com tranca, contas, sessão |
| `app.js`, `app.css` | Interface (subir `PA_VERSAO` em `_lib.php` ao mudá-los) |
| `ferramentas/excel_para_json.py` | Converte a listagem em Excel no ficheiro a importar |

## Pôr a funcionar

1. Copiar os ficheiros para o alojamento (PHP 8.1+, com `fileinfo`). A pasta `dados/` cria-se
   sozinha com um `.htaccess` que a fecha; em nginx, bloquear `/dados/` à mão.
2. Abrir o endereço: a 1.ª pessoa cria a conta de administrador (password ≥ 10).
3. Menu ☰ › **Importar listagem…** e escolher `alanol-listagem.json`.
   Só entram peças e modelos que ainda não existem: importar outra vez não apaga o que já se
   preencheu.
4. Menu ☰ › **Contas** para criar as contas das outras pessoas.

Para gerar o ficheiro a partir de uma listagem nova:

```
pip install openpyxl
python3 ferramentas/excel_para_json.py "Poço - listagem de peças.xlsx" alanol-listagem.json
```

## Testar no computador

```
php -S 127.0.0.1:8120 -t .
```

e abrir http://127.0.0.1:8120/. Apagar a pasta `dados/` depois.

## Dados

- `dados/pecas.php`, `dados/modelos.php`: JSON, uma entrada por código (começam por `exit`, para não se lerem pelo browser).
- `dados/ficheiros/`: desenhos e fotos (nome aleatório). Um ficheiro só se apaga quando
  nenhuma ficha o usa.
- `dados/contas.php`: contas (só o resumo da password).
- Fazer cópia de segurança da pasta `dados/` inteira.
