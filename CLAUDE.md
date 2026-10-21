# Reglas del proyecto

## Commits y Git

- Todos los commits van con el autor del proyecto: `leonardomover <leonardomover@gmail.com>`.
  Configurar antes de commitear:
  ```
  git config user.name "leonardomover"
  git config user.email "leonardomover@gmail.com"
  ```
- No firmar commits con Claude ni con ninguna identidad de Claude/Anthropic.
- No agregar `Co-Authored-By: Claude`, `Claude-Session:`, "Generated with Claude Code" ni ninguna otra
  mención a Claude en mensajes de commit, PRs o comentarios. Claude no debe aparecer como colaborador.
- No crear ramas nuevas. Trabajar y pushear directamente en `main`.
