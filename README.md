README
======

MySQL Workbench Schema Exporter for Doctrine2 Annotations

ATTENTION
---------

This fork will only focus on Doctrine 2 Annotation (http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/reference/annotations-reference.html) exports for use in Symfony 2 projects. 

Main changes for this fork
--------------------------
  * Option to generate base classes
  * Other multi reference handling. Given foreign key is used instead of a naming convention.

Prerequisites
-------------

  * PHP 5.3+
  * Composer to install the dependencies

Command Line Interface (CLI)
----------------------------

There is a new CLI to simplify the export process named `mwbs-export.php`, you can look under the `cli` folder.
The CLI has feature to customize export configuration before exporting. By default, CLI application will
use config file `export.json` located in the current directory to supply the parameter if it find it. To
disable this behaviour, see the option below.

The syntax of CLI:

    php cli/export.php [options] FILE [DEST]

Where:

  * `options`:
    * `--export=type`, choose the result of the export, currently available types:
      * `doctrine2-annotation`, Doctrine 2.0 Annotation classes
    * `--config=file`, read export parameters from file (in JSON format)
    * `--saveconfig`, save export parameters to file `export.json`, later can be used as value for `--config=file`
    * `--list-exporter`, show all available exporter
    * `--no-auto-config`, disable automatic config file lookup
    * `--zip`, compress the result
    * `--help`, show the usage (or suppress any parameters)
  * `FILE`, the mwb file to export
  * `DEST`, the destination directory (optional), if not specified current directory assumed

Sample usage:

    php cli/export.php --export=doctrine2-annotation example/data/test.mwb ./generated
    php cli/export.php --zip example/data/test.mwb

Sample export paramaters (JSON) for doctrine2-annotation:

    {
        "export": "doctrine2-annotation",
        "zip": false,
        "dir": "temp",
        "params": {
            "generateBaseClasses": true,
            "backupExistingFile": true,
            "skipPluralNameChecking": false,
            "enhanceManyToManyDetection": true,
            "bundleNamespace": "",
            "entityNamespace": "",
            "repositoryNamespace": "",
            "useAnnotationPrefix": "ORM\\",
            "useAutomaticRepository": true,
            "indentation": 4,
            "filename": "%entity%.%extension%",
            "quoteIdentifier": false
        }
    }

Exporter Options
----------------

### Exporter options

  * `indentation`

    The indentation size for generated code.

  * `useTabs`

    Use tabs for indentation instead of spaces. Setting this option will ignore the `indentation`-option

  * `filename`

    The output filename format, use the following tag `%schema%`, `%table%`, `%entity%`, and `%extension%` to allow
    the filename to be replaced with contextual data. Default is `%entity%.%extension%`.

  * `skipPluralNameChecking`

    Skip checking the plural name of model and leave as is, useful for non English table names. Default is `false`.

  * `backupExistingFile`

    If target already exists create a backup before replacing the content. Default is `true`.

  * `enhanceManyToManyDetection`

    If enabled, many to many relations between tables will be added to generated code. Default is `true`.

  * `logToConsole`

    If enabled, output the log to console. Default is `false`.

  * `logFile`

    If specified, output the log to a file. If this option presence, option `logToConsole` will be ignored instead. Default is empty.

  * `generateBaseClasses`

    Generate Entity Base Classes
    
  * `useAnnotationPrefix`

    Doctrine annotation prefix. Default is `ORM\`.

  * `useAutomaticRepository`

    See above.

  * `bundleNamespace`

    See above.

  * `entityNamespace`

    See above.

  * `repositoryNamespace`

    See above.

  * `skipGetterAndSetter`

    Don't generate columns getter and setter. Default is `false`.

  * `generateEntitySerialization`

    Generate method `__sleep()` to include only real columns when entity is serialized. Default is `true`.

  * `quoteIdentifier`

    If this option is enabled, all table names and column names will be quoted using backtick (`` ` ``). Usefull when the table name or column name contains reserved word. Default is `false`.

### MySQL-Workbench Comment behavior

  * `{MwbExporter:external}true{/MwbExporter:external}` (applied to Table, View)

    Mark table/view as external to skip table/view code generation. For Doctrine use `{d:external}true{/d:external}` instead.

  * `{d:bundleNamespace}AcmeBundle{/d:bundleNamespace}` (applied to Table)

    Override `bundleNamespace` option.

  * `{d:m2m}false{/d:m2m}` (applied to Table)

    MySQL Workbench schema exporter tries to automatically guess which tables are many-to-many mapping tables and will not generate entity classes for these tables.
    A table is considered a mapping table, if it contains exactly two foreign keys to different tables and those tables are not many-to-many mapping tables.

    Sometimes this guessing is incorrect for you. But you can add a hint in the comment of the table, to show that it is no mapping table. Just use "{d:m2m}false{/d:m2m}" anywhere in the comment of the table.

  * `{d:unidirectional}true{/d:unidirectional}` (applied to ForeignKey)

    All foreign keys will result in a bidirectional relation by default. If you only want a unidirectional relation, add a flag to the comment of the foreign key.

  * `{d:owningSide}true{/d:owningSide}` (applied to ForeignKey)

    In a bi-directional many-to-many mapping table the owning side of the relation is randomly selected. If you add this hint to one foreign key of the m2m-table, you can define the owning side for Doctrine.

  * `{d:cascade}persist, merge, remove, detach, all{/d:cascade}` (applied to ForeignKey)

    You can specify Doctrine cascade options as a comment on a foreign key. The will be generated into the Annotation. ([Reference](http://doctrine-orm.readthedocs.org/en/latest/reference/working-with-associations.html#transitive-persistence-cascade-operations))

  * `{d:fetch}EAGER{/d:fetch}` (applied to ForeignKey)

    You can specify the fetch type for relations in the comment of a foreign key. (EAGER or LAZY, doctrine default is LAZY)

  * `{d:orphanRemoval}true{/d:orphanRemoval}` (applied to ForeignKey)

    Another option you can set in the comments of foreign key. ([Reference](http://doctrine-orm.readthedocs.org/en/latest/reference/working-with-associations.html#orphan-removal))

Links
-----
  * [MySQL Workbench](http://wb.mysql.com/)
  * [Doctrine Project](http://www.doctrine-project.org/)
  * [Symfony Project](http://www.symfony.com/)
