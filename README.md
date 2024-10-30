# **Easy Config**
***A command to generate environment-specific configuration files***

Takes YAML files as input, resolves config values for the requested environment, and applies those values to a template.
It allows you to inherit common settings and selectively override only what’s necessary.
It reduces the need to maintain duplicate settings files for different environments.

A configuration entry with params:
```yaml
acme:Website:                  # <entry-name>:<type>:
  host: acme.devel             #   <param-name>: <value>
  host@prod: acme.com          #   <param-name>@<env-base>: <value>
  host@prod-1: acme.com        #   <param-name>@<env>: <value>
  host@prod-2: acme.net        #   <param-name>@<env>: <value>
```

Will be resolved for the `prod-2` environment as (`cat acme.yml | ez-cfg prod-2 --resolve`):
```yaml
acme:Website:
  host: acme.net
```

Step-by-step here's what it does:
- Ingests YAML/[eYAML](# "Just eJSON converted to YAML") or JSON/[eJSON](https://github.com/Shopify/ejson) configuration files
- Resolves the configuration entries for your specific environment, merging them with the base-environment defaults, and environment-unspecific defaults
- Resolves defaults for your configuration entry, merging them with the entry's type defaults, and global defaults
- Applies each configuration entry to a template engine to generate the final configuration files


### Installation

```bash
composer global require curatorium/easy-config
```

#### Requirements
- PHP >= 8.2
- PHP extension for YAML
- for eJSON you need to install [ejson](https://github.com/Shopify/ejson) globally
- for eYAML you need to install [yq](https://github.com/mikefarah/yq) and the `eyaml` command (provided by this package) globally

### Usage
Pure command line:
```bash
easy-config --template=templates/ --extension=tpl prod-2 -- *.json *.ejson *.yaml *.eyaml
```
...or via configuration file `.easy-config`:
```yaml
in-files: ['*.json', '*.ejson', '*.yaml', '*.eyaml']
out-files: '${env}/${tags.type}/${tags.name}.conf'

templates: templates/
```

### Syntax
```yaml
# Configuration entry: a named map containing several parameters.
#
#  the name of the config entry (**required**)
#  |
#  |     type specifier (**required**)
#  ▼     ▼
<name>:<type>@<env>:
#               ▲
#             environment specifier (**optional**); when omitted, the entry/param, will apply to all environments;
#             ▼
    <param>@<env>: <value>
#      ▲
#      parameter name (**required**)

#      Entry tags (key/value, implicitly includes: type, module, feature, name, fullname, ext)
#      ▼
    :<tag>: <value>

# Type defaults: a named map containing default parameters shared by all entries of that type.
#
#  type specifier (**required**)
#  ▼
:<type>@<env>:
    <param>@<env>: <value>


# Global defaults: a named map containing default parameters shared by all entries.
#
#   type name Default is reserved for global defaults
#   ▼
:Default@<env>:
    <param>@<env>: <value>
```

#### Note:
`<env>` can be composed of a prefix (called base environment, ex.: 'test', 'prod') followed by a `-` and a suffix.
Base environments can add another layer of defaults that the full environment can inherit.
