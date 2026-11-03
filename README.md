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

## 3. Crie as tabelas

```sql
CREATE TABLE users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    lastname VARCHAR(100),
    email VARCHAR(200),
    password VARCHAR(200),
    image VARCHAR(200),
    token VARCHAR(200),
    bio TEXT
);
```
```sql
CREATE TABLE movies (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100),
    description TEXT,
    image VARCHAR(200),
    trailer VARCHAR(150),
    category VARCHAR(50),
    length VARCHAR(50),
    users_id INT(11) UNSIGNED,
    FOREIGN KEY(users_id) REFERENCES users(id)
);
```
```sql
CREATE TABLE reviews (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rating INT,
    review TEXT,
    users_id INT(11) UNSIGNED,
    movies_id INT(11) UNSIGNED,
    FOREIGN KEY (users_id) REFERENCES users(id),
    FOREIGN KEY (movies_id) REFERENCES movies(id)
);
```


3. Copie `config/database.example.php` para `config/database.php` e ajuste as credenciais se necessario (padrao: `root` sem senha). O arquivo `config/database.php` nao e versionado.

4. Inicie o Apache e o MySQL pelo painel do WAMP (icone verde na bandeja do sistema).

5. Acesse no navegador:
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