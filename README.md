# MovieStar

Aplicação web para catálogo, busca e avaliação de filmes desenvolvida com PHP "puro" (orientado a objetos, sem frameworks) e MySQL.

## Tecnologias

- **PHP** (sem frameworks)
- **MySQL** com PDO
- **Bootstrap 4**
- **Font Awesome 7**
- **jQuery**
- **WAMP** (ambiente local)

## Funcionalidades

- **Conta de usuário**: cadastro, login e logout. O login tem limite de tentativas: 5 falhas por e-mail e 20 por IP em janela de 15 minutos.
- **Filmes**: cadastro com upload de imagem de capa (JPG, PNG ou WebP), edição e exclusão. Cada usuário gerencia os próprios filmes pelo painel **"Meus filmes"**; o acesso a páginas de edição é verificado no servidor.
- **Home**: últimos filmes cadastrados e listagem por categoria.
- **Página do filme**: detalhes, trailer do YouTube (incorporado) e média das avaliações.
- **Avaliações**: cada usuário avalia um filme com nota inteira de 1 a 10 e comentário opcional, com uma avaliação por filme e usuário (o dono não avalia o próprio filme).
- **Busca**: pesquisa de filmes pelo cabeçalho do site.
- **Perfil**: página pública do usuário com os filmes cadastrados por ele, e edição de nome, biografia, foto e senha.
- **Paginação** nas listagens (home por categoria, busca e painel).
- **Tratamento de erros**: página de erro genérica para o visitante; o detalhe técnico vai para `logs/app.log`.
- **Segurança**: senhas com `password_hash()`, proteção CSRF em todos os formulários, escape de saída em HTML, validação de uploads pelo MIME real (não pelo nome do arquivo), cookies de sessão com `HttpOnly` e `SameSite=Lax`, cabeçalhos de segurança (CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`) e bloqueio de acesso HTTP a `config/`, `src/`, `database/`, `logs/` e arquivos ocultos.

## Estrutura do Projeto

```
MovieStar/
├── index.php            Home: últimos filmes e listagem por categoria
├── auth.php             Login, cadastro e logout
├── movie.php            Página do filme, trailer, média e avaliações
├── newmovie.php         Cadastro de filme
├── editmovie.php        Edição e exclusão de filme (somente o dono)
├── dashboard.php        Painel "Meus filmes"
├── search.php           Resultados da busca
├── profile.php          Perfil público do usuário
├── editprofile.php      Edição de dados, foto e senha
├── bootstrap.php        Inicialização: sessão, banco, serviços e tratamento de erros
├── autoload.php         Autoload PSR-4 simples (namespace MovieStar\ -> src/)
├── .htaccess            Regras de segurança e cabeçalhos do Apache
├── config/              Configurações: exemplos versionados e arquivos reais (não versionados)
├── css/                 Estilos próprios (styles.css), sobre o Bootstrap
├── database/            schema.sql com a criação das tabelas
├── img/                 Logo, ícone e imagens padrão
│   ├── movies/          Capas dos filmes enviadas pelos usuários
│   └── users/           Fotos de perfil enviadas pelos usuários
├── js/                  Scripts da interface (app.js)
├── logs/                Log de erros (app.log), criado em tempo de execução
├── src/                 Código PHP orientado a objetos
│   ├── Controllers/     Processam requisições e chamam os DAOs
│   ├── Core/            Infraestrutura: banco, sessão, autenticação, upload, paginação, erros
│   ├── DAO/             Acesso ao banco (único lugar com SQL, via PDO e prepared statements)
│   └── Models/          Entidades de dados: Movie, Review, User
└── templates/           Partes reutilizáveis de HTML (cabeçalho, rodapé, cards, formulários, paginação)
```

## Configuração

### Pré-requisitos

- **PHP 7.4+** com as extensões `pdo_mysql` e `fileinfo` (habilitadas por padrão no WAMP)
- **MySQL 5.7+** ou **MariaDB** (ou o MySQL embutido no WAMP)
- **Apache 2.4** com:
  - `AllowOverride All` no diretório do projeto (o `.htaccess` é obrigatório para as regras de segurança e para o `mod_rewrite`);
  - módulos `mod_rewrite` e `mod_headers` ativos (já vêm ativos no WAMP).

### Instalação

1. Clone o repositório dentro da pasta `www` do WAMP:
   ```bash
   git clone <url-do-repositorio> C:/wamp64/www/MovieStar
   ```

2. Crie o banco de dados no MySQL:
   ```sql
   CREATE DATABASE moviestar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Crie as tabelas importando o arquivo `database/schema.sql` no banco `moviestar`, pelo phpMyAdmin (aba Importar) ou pela linha de comando:
   ```bash
   mysql -uroot moviestar < database/schema.sql
   ```
   O script cria as tabelas `users`, `movies`, `reviews` e `login_attempts` (usada pelo limite de tentativas de login).

4. Configure o banco e o modo da aplicação:
   - Copie `config/database.example.php` para `config/database.php` e preencha host, nome do banco, usuário e senha.
   - Copie `config/app.example.php` para `config/app.php`. Em desenvolvimento, use `'debug' => true` para ver os detalhes dos erros na tela.

   Os arquivos `config/database.php` e `config/app.php` não são versionados e não devem ser enviados ao repositório.

5. Garanta que o Apache consiga gravar nas pastas de upload e de log:
   - `img/movies/` (capas dos filmes);
   - `img/users/` (fotos de perfil);
   - `logs/` (arquivo `app.log`).

   No WAMP, normalmente isso já funciona. Em outros ambientes, conceda permissão de escrita ao usuário que roda o Apache.

6. Inicie o Apache e o MySQL pelo painel do WAMP (ícone verde na bandeja do sistema).

7. Acesse no navegador:
   ```
   http://localhost/MovieStar/
   ```

## Produção

Antes de publicar, revise os pontos abaixo:

- **Modo de depuração desligado**: em `config/app.php`, use `'debug' => false` (ou remova o arquivo). Com `debug` ligado, erros mostram detalhes internos na tela.
- **HSTS não está ligado por padrão**: o cabeçalho `Strict-Transport-Security` só deve ser enviado quando o site responde somente por HTTPS. Se ativá-lo em `.htaccess` (ex.: `Header always set Strict-Transport-Security "max-age=31536000"`), faça isso apenas com HTTPS funcionando, pois o cabeçalho afeta todos os sites do mesmo host, inclusive em `localhost`.
- **Atrás de proxy reverso**: o limite de tentativas de login por IP usa `REMOTE_ADDR`. Por trás de um proxy, todos os usuários aparecem com o IP do proxy, então o limite por IP passa a valer para todos juntos. Além disso, para o cookie de sessão ser marcado como seguro, o servidor precisa receber `HTTPS=on` do proxy.
- **Permissões**: restrinja a escrita às pastas `img/movies/`, `img/users/` e `logs/`.

## Limitações conhecidas

- O cadastro informa se um e-mail já está em uso (isso permite descobrir contas existentes).
- O limite de tentativas é por conta: quem conhece um e-mail consegue bloquear o login daquela conta por 15 minutos.
- Dois cadastros simultâneos com o mesmo e-mail podem gerar erro 500 em vez de uma mensagem amigável.
- O código foi testado no PHP 8.3; a compatibilidade com PHP 7.4 é a meta declarada, mas ainda não foi verificada em PHP 7.4.

## Banco de Dados

| Parâmetro | Valor padrão |
|-----------|--------------|
| Host      | localhost    |
| Database  | moviestar    |
| User      | root         |
| Password  | *(vazio)*    |
