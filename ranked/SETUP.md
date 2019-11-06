Create user on clantool DB without full access permissions

First add the SQL entries in setup.sql for this mod.

Then create an additional user and grant specific permissions to the ranked tables

```sql
CREATE USER 'ranked'@'localhost' IDENTIFIED BY 'password';
GRANT INSERT,SELECT,UPDATE,DELETE ON clantool.ranks TO `ranked`@`localhost`;
GRANT INSERT,SELECT,UPDATE,DELETE ON clantool.mode_names TO `ranked`@`localhost`;
GRANT INSERT,SELECT,UPDATE,DELETE ON clantool.rank_names TO `ranked`@`localhost`;
GRANT SELECT ON clantool.ranked TO `ranked`@`localhost`;
GRANT SELECT ON clantool.member_names TO `ranked`@`localhost`;
GRANT SELECT ON clantool.member TO `ranked`@`localhost`;
```
