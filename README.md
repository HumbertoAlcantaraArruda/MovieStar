# MovieStar

Aplicação web para catálogo, busca e avaliação de filmes desenvolvido com PHP "puro" e MySQL.

## Tecnologias

- **PHP** (sem frameworks)
- **MySQL** com PDO
- **Bootstrap 4**
- **Font Awesome 7**
- **jQuery**
- **WAMP** (ambiente local)

## Funcionalidades
Em desenvolvimento...

## Estrutura do Projeto
Em desenvolvimento...

## Configuracao

### Pre-requisitos

- WAMP (Apache + MySQL)
- PHP 7.4+

### Instalacao

1. Clone o repositorio dentro da pasta `www` do WAMP:
   ```bash
   git clone <url-do-repositorio> C:/wamp64/www/MovieStar
   ```

2. Crie o banco de dados no MySQL:
   ```sql
   CREATE DATABASE moviestar;
   ```

3. Crie as tabelas importando o arquivo `database/schema.sql` no banco `moviestar`, pelo phpMyAdmin (aba Importar) ou pela linha de comando:
   ```bash
   mysql -uroot moviestar < database/schema.sql
   ```

4. Copie `config/database.example.php` para `config/database.php` e ajuste as credenciais se necessario (padrao: `root` sem senha). O arquivo `config/database.php` nao e versionado.

   Copie tambem `config/app.example.php` para `config/app.php` e use `'debug' => true` em desenvolvimento (mostra detalhes dos erros). Sem esse arquivo, ou com `false`, o app mostra uma pagina de erro generica e grava o detalhe em `logs/app.log`.

5. Inicie o Apache e o MySQL pelo painel do WAMP (icone verde na bandeja do sistema).

6. Acesse no navegador:
   ```
   http://localhost/MovieStar/
   ```

## Banco de Dados

| Parametro | Valor padrao |
|-----------|-------------|
| Host      | localhost   |
| Database  | moviestar   |
| User      | root        |
| Password  | *(vazio)*   |