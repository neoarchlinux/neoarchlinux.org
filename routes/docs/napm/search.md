# search - Search for packages

```bash
napm search <term> [<term>...]
napm search -n <number> <term> [<term>...]
```

Results are ranked by relevance using a TF-IDF scoring algorithm with fuzzy matching. The search:

1. Tokenises and lowercases the query.
2. Expands each query token with dictionary words within Levenshtein distance 2.
3. Queries the SQLite cache for candidates matching any expanded token in name or description.
4. Scores candidates - exact name match scores higher (×5 IDF) than description match (×1.5 IDF), with additional fuzzy-match bonuses.
5. Returns results sorted by score, highest first (displayed bottom-to-top so the best match is at the bottom of the terminal).

Use `-n` / `--num-results` to limit the number of results shown.

**Example:**

```
$ napm search http server
 ...
 - [3] apache-2.4.62-1 [extra] Apache HTTP Server
 - [2] nginx-1.26.1-1 [extra] Lightweight HTTP server and IMAP/POP3 proxy server
 - [1] caddy-2.8.4-1 [extra] Fast, multi-platform web server with automatic HTTPS
```

The cache must exist (run `napm update --files` first if it is missing).
