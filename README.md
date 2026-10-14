# Convite Premier · 10 anos

Convite online com link exclusivo por convidado, confirmação de presença (RSVP) e painel de gestão.
Feito em PHP puro + MySQL para rodar em **hospedagem compartilhada da Hostinger**, sem Composer, Node ou build.

## O que o sistema faz

**Para o convidado**
- Convite no visual do modelo (logo, data, horário, local, traje), otimizado para celular.
- Link único por pessoa (`seusite.com.br/c/Ab3xYz9kQe`) com saudação personalizada ("Olá, Maria").
- Quem abre o site sem o link pessoal, ou com um link errado, vê só um aviso para usar o link recebido. Data, horário e local não aparecem.
- Tratamento por convidado: *"Você é nosso convidado"*, *"Você é nossa convidada"* ou *"Vocês são nossos convidados"*.
- Confirmar ou recusar, informar acompanhantes (até o limite definido para cada um) e deixar um recado.
- Após confirmar: botão para salvar na Agenda Google e opção de alterar a resposta.
- Prazo de confirmação opcional. Depois dele, o convite não aceita respostas.
- Prévia bonita do link no WhatsApp (imagem e título).

**Para a equipe (painel em `/admin`)**
- Indicadores: convidados, pessoas confirmadas (com acompanhantes), recusas, pendentes, enviados, quem abriu o link.
- Lista com busca e filtros (resposta, grupo, enviados, enviados e não abertos).
- Envio pelo WhatsApp com mensagem pronta e personalizada. O convite é marcado como enviado automaticamente.
- Cadastro individual ou importação em massa por planilha CSV ou colando uma lista de nomes.
- Exportação para Excel (CSV) com respostas, acompanhantes, recados e links.
- Alteração manual da resposta, para quem confirmou por telefone.
- Gerar novo link, que invalida o anterior, caso um link vaze.
- Página de **check-in** para o dia do evento, com busca instantânea.
- Configurações do evento e da mensagem do WhatsApp editáveis pelo painel.
- Atividade recente: quem abriu, confirmou ou recusou.

## Instalação na Hostinger

1. **Crie o banco MySQL.** No hPanel, vá em *Bancos de dados → Gerenciamento* e crie um banco e um usuário. Anote o nome do banco, o usuário e a senha.
2. **Envie os arquivos.** Em *Arquivos → Gerenciador de arquivos*, abra `public_html` (ou a pasta do subdomínio) e envie todo o conteúdo deste repositório, incluindo o arquivo oculto `.htaccess`. A forma mais simples é enviar um `.zip` e extrair lá.
3. **Ative o SSL** do domínio em *Segurança → SSL*.
4. **Rode o instalador.** Acesse `https://seudominio.com.br/install.php`, preencha os dados do banco e crie o usuário e a senha do painel.
5. Pronto. Acesse `https://seudominio.com.br/admin/`. Opcionalmente, apague o `install.php`. Ele já se bloqueia sozinho depois da instalação.

> Também é possível escolher **SQLite** no instalador. Nesse caso não é preciso criar banco, mas o MySQL é o recomendado.

### Forçar HTTPS
Depois que o SSL estiver ativo, descomente as duas linhas indicadas no `.htaccess`.

### Trocar o domínio depois
Edite `base_url` em `app/config.php`. Os códigos dos links não mudam, só o domínio.

### Se os links `/c/...` derem erro 404
O `.htaccess` não foi enviado ou foi enviado com outro nome. Como alternativa, defina `'pretty_urls' => false` em `app/config.php`. Os links passam a ser `seusite.com.br/?c=CODIGO`.

## Planilha para importação

Salve como CSV (separado por `;` ou `,`). Só a coluna `nome` é obrigatória:

| nome | telefone | email | grupo | acompanhantes | tratamento |
|---|---|---|---|---|---|
| Maria Silva | (31) 99999-0001 | maria@exemplo.com | Diretoria | 1 | convidada |
| João e Ana Souza | (31) 99999-0002 | | Parceiros | 0 | plural |

Há um botão para baixar este modelo na tela **Importar**. Arquivos salvos pelo Excel em ANSI são convertidos automaticamente.

## Mensagem do WhatsApp

Editável em **Configurações**. Variáveis disponíveis:

| Variável | Vira |
|---|---|
| `{nome}` / `{primeiro_nome}` | nome completo / primeiro nome |
| `{link}` | link exclusivo do convidado |
| `{data}` / `{horario}` | data e horário do evento |
| `{voce}` | "você" ou "vocês" |
| `{convidado}` | "nosso convidado", "nossa convidada" ou "nossos convidados" |

## Estrutura

```
index.php          convite público (lê o código do link)
install.php        instalador
admin/             painel (lista, cadastro, importar, exportar, check-in, configurações)
app/               código interno, bloqueado para acesso web
  config.php       criado pelo instalador (não vai para o Git)
  views/invite.php layout do convite
assets/            CSS, JS e imagens (logo, ícone, imagem de prévia)
```

## Segurança
- Senha com `password_hash`, sessão `HttpOnly`/`SameSite` e proteção CSRF em todo o painel.
- Bloqueio após 8 tentativas de login erradas em 15 minutos.
- Códigos de convite aleatórios com 10 caracteres, cerca de 360 quatrilhões de combinações.
- A pasta `app/` e arquivos de banco são bloqueados pelo `.htaccess`.
- Visualizações feitas pelo administrador logado não contam como abertura do convidado.

## Requisitos
PHP 7.4 ou superior (a Hostinger usa PHP 8.x) com PDO MySQL ou SQLite, mbstring e `mod_rewrite`/LiteSpeed.
