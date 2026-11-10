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

## Fotos do evento (pós-evento)

No menu **Fotos** do painel:

1. **Envie as fotos.** Arraste para a área de envio. Aceita JPG, PNG e WebP. Cada foto gera uma versão para tela e uma miniatura, e a original fica disponível para download.
2. **Cole o link de avaliação do Google.** Ele fica no Perfil da Empresa do hospital, em "Pedir avaliações".
3. **Ative o modo fotos.** A partir daí, o link pessoal de cada convidado abre a página de fotos no lugar do convite.

Na página de fotos, o convidado vê a mensagem, o botão "Avaliar no Google" e uma contagem regressiva (20 segundos por padrão). Ao fim da contagem, ele libera a galeria, amplia as fotos e baixa cada uma. Quem já liberou não espera de novo. O tempo de espera também é conferido no servidor.

Enquanto o modo fotos está ativo, o botão WhatsApp da lista envia a mensagem das fotos, e a coluna Envio passa a mostrar o envio dessa mensagem. O painel mostra quem abriu a página, quem viu as fotos e quem clicou em avaliar. Desative o modo fotos para os links voltarem a mostrar o convite.

> O Google proíbe oferecer qualquer benefício em troca de avaliações. Por isso o texto padrão convida a avaliar sem dizer que a avaliação é obrigatória, e o site não verifica se a pessoa avaliou. Todos os textos podem ser editados no painel.

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
fotos.php          libera a galeria e registra o clique em avaliar
install.php        instalador
admin/             painel (lista, cadastro, importar, exportar, check-in, configurações)
app/               código interno, bloqueado para acesso web
  config.php       criado pelo instalador (não vai para o Git)
  views/invite.php layout do convite
assets/            CSS, JS e imagens (logo, ícone, imagem de prévia)
uploads/fotos/     fotos enviadas pelo painel (não vão para o Git)
```

## Segurança
- Senha com `password_hash`, sessão `HttpOnly`/`SameSite` e proteção CSRF em todo o painel.
- Bloqueio após 8 tentativas de login erradas em 15 minutos.
- Códigos de convite aleatórios com 10 caracteres, cerca de 360 quatrilhões de combinações.
- A pasta `app/` e arquivos de banco são bloqueados pelo `.htaccess`.
- Visualizações feitas pelo administrador logado não contam como abertura do convidado.

## Requisitos
PHP 7.4 ou superior (a Hostinger usa PHP 8.x) com PDO MySQL ou SQLite, mbstring e `mod_rewrite`/LiteSpeed.
