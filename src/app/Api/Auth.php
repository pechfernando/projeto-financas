<?php

/**
 * TEMPORÁRIO: enquanto não implementamos login (autenticação), toda
 * a aplicação assume que está sendo usada pelo usuário de id 1
 * (o "Usuário Teste" criado no seed.sql).
 *
 * Quando implementarmos a Fase de autenticação, essa função vai passar
 * a ler o usuário logado de verdade (a partir de um token/sessão),
 * e o resto do código (Controllers, Models) não precisa mudar nada,
 * porque todos já dependem dessa função central.
 */
function usuarioAtualId(): int
{
    return 1;
}
