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
├── templates/           Partes reutilizáveis de HTML (cabeçalho, rodapé, cards, formulários, paginação)
└── tests/               Testes automatizados em PHP puro (run.php, Unit/, Integration/, Support/, cgi/)
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

## Testes

Suíte própria em PHP puro (sem Composer nem PHPUnit), rodada pela linha de comando na raiz do projeto:

```bash
php tests/run.php              # todos os testes
php tests/run.php YouTube      # só os testes cujo "Classe::metodo" contém o texto
php tests/run.php --keep-db    # não apaga o banco de teste no fim (para inspecionar)
```

- **Unitários** (`tests/Unit/`): `LoginThrottle`, `Session` (CSRF, flash), `YouTube`, `UserRules`, regras do `ImageUploader` e uma checagem de sintaxe exclusiva do PHP 8 (o projeto deve rodar no 7.4).
- **Integração** (`tests/Integration/`): DAOs e controllers contra o banco de teste, e uploads reais de imagem.
- **Banco de teste**: usa as credenciais de `config/database.php` com o nome do banco acrescido de `_test` (ex.: `moviestar_test`). O runner o recria a partir de `database/schema.sql` a cada execução e o apaga no fim; o banco principal nunca é usado. O usuário do MySQL precisa de permissão para criar e apagar esse banco.
- **Controllers e uploads** rodam em um processo `php-cgi` separado (o `php-cgi` que fica ao lado do `php` usado, como no WAMP), sem servidor web; os arquivos enviados vão para uma pasta temporária do sistema, nunca para `img/`. Sem `php-cgi`, esses testes aparecem como pulados.
- Saída: um resumo com testes que passaram/falharam/foram pulados; o código de saída é `0` quando tudo passa e `1` quando algo falha.
- A pasta `tests/` é bloqueada para acesso HTTP pelo `.htaccess`.

## Banco de Dados

| Parâmetro | Valor padrão |
|-----------|--------------|
| Host      | localhost    |
| Database  | moviestar    |
| User      | root         |
| Password  | *(vazio)*    |
