<?php

/*
 * The MIT License
 *
 * Copyright (c) 2010 Johannes Mueller <circus2(at)web.de>
 * Copyright (c) 2012-2013 Toha <tohenk@yahoo.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace MwbExporter\Formatter\Doctrine2\Annotation\Model;

use MwbExporter\Formatter\Doctrine2\Model\Column as BaseColumn;
use Doctrine\Common\Inflector\Inflector;
use MwbExporter\Writer\WriterInterface;
use MwbExporter\Formatter\Doctrine2\Annotation\Formatter;

class Column extends BaseColumn
{
    public function asAnnotation()
    {
        $attributes = array(
            'name' => $this->getTable()->quoteIdentifier($this->getColumnName()),
            'type' => $this->getDocument()->getFormatter()->getDatatypeConverter()->getMappedType($this),
        );
        if (($length = $this->parameters->get('length')) && ($length != -1)) {
            $attributes['length'] = (int) $length;
        }
        if ($this->isUnique) {
            $attributes['unique'] = true;
        }
        if (($precision = $this->parameters->get('precision')) && ($precision != -1) && ($scale = $this->parameters->get('scale')) && ($scale != -1)) {
            $attributes['precision'] = (int) $precision;
            $attributes['scale'] = (int) $scale;
        }
        if (!$this->isNotNull()) {
            $attributes['nullable'] = true;
        }  else {
            $attributes['nullable'] = false;
        }
        if($this->isUnsigned()) {
            $attributes['options'] = array('unsigned' => true);
        }
        if ($this->parameters->get('comment')) {
        	$attributes['options']["comment"] = $this->getComment(false);
        }

        return $attributes;
    }

    public function write(WriterInterface $writer)
    {
        $comment = $this->getComment();

        $converter = $this->getDocument()->getFormatter()->getDatatypeConverter();
        $nativeType = $converter->getNativeType($converter->getMappedType($this));

        $generateBaseClasses = $this->getDocument()->getConfig()->get(Formatter::CFG_GENERATE_BASE_CLASSES);

        $accessModifier = $generateBaseClasses ? 'protected' : 'private';
        $default_value = $this->getDefaultValueAsPhp();

        if ($default_value !== null) {
            $default_value = ' = ' . $default_value;
        } else {
            $default_value = '';
        }

        $writer
            ->write('/**')
            ->writeIf($comment, $comment)
            ->write(' * @var '.$nativeType)
            ->writeIf($this->isPrimary,
                    ' * '.$this->getTable()->getAnnotation('Id'))
            ->write(' * '.$this->getTable()->getAnnotation('Column', $this->asAnnotation()))
            ->writeIf($this->isAutoIncrement(),
                    ' * '.$this->getTable()->getAnnotation('GeneratedValue', array('strategy' => 'AUTO')))
            ->write(' */')
            ->write($accessModifier.' $'.$this->getPhpColumnName().$default_value.';')
            ->write('')
        ;

        return $this;
    }

    public function writeConstructor(WriterInterface $writer)
    {
        return $this;
    }

    public function writeArrayCollection(WriterInterface $writer)
    {
        foreach ($this->foreigns as $foreign) {
            if ($foreign->getForeign()->getTable()->isManyToMany()) {
                // do not create entities for many2many tables
                continue;
            }

            if ($foreign->isManyToOne() && $foreign->parseComment('unidirectional') !== 'true') { // is ManyToOne
                $related = $this->getRelatedName($foreign);
                $writer->write('$this->%s = new %s();', lcfirst(Inflector::pluralize($foreign->getOwningTable()->getModelName())).$related, $this->getTable()->getCollectionClass(false));
            }
        }

        return $this;
    }

    public function writeRelations(WriterInterface $writer)
    {
        $formatter = $this->getDocument()->getFormatter();
        // one to many references
        foreach ($this->foreigns as $foreign) {
            if ($foreign->getForeign()->getTable()->isManyToMany()) {
                // do not create entities for many2many tables
                continue;
            }
            if ($foreign->parseComment('unidirectional') === 'true') {
                // do not output mapping in foreign table when the unidirectional option is set
                continue;
            }

            $targetEntity = $foreign->getOwningTable()->getModelName();
            $targetEntityFQCN = $foreign->getOwningTable()->getModelNameAsFQCN($foreign->getReferencedTable()->getEntityNamespace());
            $mappedBy = $foreign->getReferencedTable()->getModelName();

            $annotationOptions = array(
                'targetEntity' => $targetEntityFQCN,
                'mappedBy' => $this->isMultiReferences($foreign) ? $this->getRelatedName($foreign) : lcfirst($mappedBy),
                'cascade' => $formatter->getCascadeOption($foreign->parseComment('cascade')),
                'fetch' => $formatter->getFetchOption($foreign->parseComment('fetch')),
                'orphanRemoval' => $formatter->getBooleanOption($foreign->parseComment('orphanRemoval')),
            );

            $joinColumnAnnotationOptions = array(
                'name' => $foreign->getForeign()->getColumnName(),
                'referencedColumnName' => $foreign->getLocal()->getColumnName(),
                'onDelete' => $formatter->getDeleteRule($foreign->getLocal()->getParameters()->get('deleteRule')),
                'nullable' => !$foreign->getForeign()->isNotNull() ? null : false,
            );

            //check for OneToOne or OneToMany relationship
            if ($foreign->isManyToOne()) { // is OneToMany
            	$name = $this->getManyToOneEntityName($foreign);

                $writer
                    ->write('/**')
                    ->write(' * '.$this->getTable()->getAnnotation('OneToMany', $annotationOptions))
                    ->write(' * '.$this->getTable()->getAnnotation('JoinColumn', $joinColumnAnnotationOptions))
                    ->write(' */')
                    ->write('protected $'. $name .';')
                    ->write('')
                ;
            } else { // is OneToOne
                $writer
                    ->write('/**')
                    ->write(' * '.$this->getTable()->getAnnotation('OneToOne', $annotationOptions))
                    ->write(' * '.$this->getTable()->getAnnotation('JoinColumn', $joinColumnAnnotationOptions))
                    ->write(' */')
                    ->write('protected $'.lcfirst($targetEntity).';')
                    ->write('')
                ;
            }
        }
        // many to references
        if (null !== $this->local) {
            $targetEntity = $this->local->getReferencedTable()->getModelName();
            $targetEntityFQCN = $this->local->getReferencedTable()->getModelNameAsFQCN($this->local->getOwningTable()->getEntityNamespace());
            $inversedBy = $this->local->getOwningTable()->getModelName();

            $annotationOptions = array(
                'targetEntity' => $targetEntityFQCN,
                'mappedBy' => null,
                'inversedBy' => $inversedBy,
                // 'cascade' => $formatter->getCascadeOption($this->local->parseComment('cascade')),
                // 'fetch' => $formatter->getFetchOption($this->local->parseComment('fetch')),
                // 'orphanRemoval' => $formatter->getBooleanOption($this->local->parseComment('orphanRemoval')),
            );
            $joinColumnAnnotationOptions = array(
                'name' => $this->local->getForeign()->getColumnName(),
                'referencedColumnName' => $this->local->getLocal()->getColumnName(),
                'onDelete' => $formatter->getDeleteRule($this->local->getParameters()->get('deleteRule')),
                'nullable' => !$this->local->getForeign()->isNotNull() ? null : false,
            );

            //check for OneToOne or ManyToOne relationship
            if ($this->local->isManyToOne()) { // is ManyToOne
                $name = lcfirst($targetEntity);
                $inversedBy = Inflector::pluralize($annotationOptions['inversedBy']);
                $refRelated = '';

                if ($this->getParent()->getManyToManyCount($this->local->getReferencedTable()->getRawTableName()) > 1) {
                	$name = $this->local->getParameters()->get('name');
                	$refRelated = $this->local->getParameters()->get('name');
                } else {
                	$inversedBy = lcfirst($inversedBy);
                }

                if ($this->local->parseComment('unidirectional') === 'true') {
                    $annotationOptions['inversedBy'] = null;
                } else {
                    $annotationOptions['inversedBy'] = $refRelated . $inversedBy;
                }
                $writer
                    ->write('/**')
                    ->write(' * '.$this->getTable()->getAnnotation('ManyToOne', $annotationOptions))
                    ->write(' * '.$this->getTable()->getAnnotation('JoinColumn', $joinColumnAnnotationOptions))
                    ->write(' */')
                    ->write('protected $'.$name.';')
                    ->write('')
                ;
            } else { // is OneToOne
                if ($this->local->parseComment('unidirectional') === 'true') {
                    $annotationOptions['inversedBy'] = null;
                } else {
                    $annotationOptions['inversedBy'] = lcfirst($annotationOptions['inversedBy']);
                }
                $annotationOptions['cascade'] = $formatter->getCascadeOption($this->local->parseComment('cascade'));

                $writer
                    ->write('/**')
                    ->write(' * '.$this->getTable()->getAnnotation('OneToOne', $annotationOptions))
                    ->write(' * '.$this->getTable()->getAnnotation('JoinColumn', $joinColumnAnnotationOptions))
                    ->write(' */')
                    ->write('protected $'.lcfirst($targetEntity).';')
                    ->write('')
                ;
            }
        }

        return $this;
    }

    public function writeGetterAndSetter(WriterInterface $writer)
    {
        $table = $this->getTable();
        $converter = $this->getDocument()->getFormatter()->getDatatypeConverter();
        $nativeType = $converter->getNativeType($converter->getMappedType($this));
        $writer
            // setter
            ->write('/**')
            ->write(' * Set the value of '.$this->getPhpColumnName().'.')
            ->write(' *')
            ->write(' * @param '.$nativeType.' $'.$this->getPhpColumnName())
            ->write(' * @return '.$table->getNamespace())
            ->write(' */')
            ->write('public function set'.$this->columnNameBeautifier($this->getColumnName()).'($'.$this->getColumnName().')')
            ->write('{')
            ->indent()
                ->write('$this->'.$this->getPhpColumnName().' = $'.$this->getColumnName().';')
                ->write('')
                ->write('return $this;')
            ->outdent()
            ->write('}')
            ->write('')
            // getter
            ->write('/**')
            ->write(' * Get the value of '.$this->getPhpColumnName().'.')
            ->write(' *')
            ->write(' * @return '.$nativeType)
            ->write(' */')
            ->write('public function get'.$this->columnNameBeautifier($this->getColumnName()).'()')
            ->write('{')
            ->indent()
                ->write('return $this->'.$this->getPhpColumnName().';')
            ->outdent()
            ->write('}')
            ->write('')
        ;

        return $this;
    }

    public function writeRelationsGetterAndSetter(WriterInterface $writer)
    {
        $table = $this->getTable();
        // one to many references
        foreach ($this->foreigns as $foreign) {
            if ($foreign->getForeign()->getTable()->isManyToMany()) {
                // do not create entities for many2many tables
                continue;
            }
            if ($foreign->parseComment('unidirectional') === 'true') {
                // do not output mapping in foreign table when the unidirectional option is set
                continue;
            }

            $targetEntity = $foreign->getOwningTable()->getModelName();
            $targetEntityFQCN = $foreign->getOwningTable()->getModelNameAsFQCN($foreign->getReferencedTable()->getEntityNamespace());

            if ($foreign->isManyToOne()) { // is ManyToOne
                $related = $this->getRelatedName($foreign);
                $related_text = $this->getRelatedName($foreign, false);

                $attribute_name = $related ? lcfirst($related).ucfirst(Inflector::pluralize($targetEntity)) : lcfirst(Inflector::pluralize($targetEntity));
                $funcion_name = $this->columnNameBeautifier($targetEntity).$related;

                $writer
                    // setter
                    ->write('/**')
                    ->write(' * Add '.trim($targetEntity.' '.$related_text). ' entity to collection (one to many).')
                    ->write(' *')
                    ->write(' * @param '.$targetEntityFQCN.' $'.lcfirst($targetEntity))
                    ->write(' * @return '.$table->getNamespace())
                    ->write(' */')
                    ->write('public function add'.$funcion_name.'('.$targetEntityFQCN.' $'.lcfirst($targetEntity).')')
                    ->write('{')
                    ->indent()
                        ->write('$this->'.$attribute_name.'[] = $'.lcfirst($targetEntity).';')
                        ->write('')
                        ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                    // remove
                    ->write('/**')
                    ->write(' * Remove '.trim($targetEntity.' '.$related_text). ' entity from collection (one to many).')
                    ->write(' *')
                    ->write(' * @param '.$targetEntityFQCN.' $'.lcfirst($targetEntity))
                    ->write(' * @return '.$table->getNamespace())
                    ->write(' */')
                    ->write('public function remove'.$funcion_name.'('.$targetEntityFQCN.' $'.lcfirst($targetEntity).')')
                    ->write('{')
                    ->indent()
                        ->write('$this->'.$attribute_name.'->removeElement($'.lcfirst($targetEntity).');')
                        ->write('')
                        ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                // getter
                    ->write('/**')
                    ->write(' * Get '.trim($targetEntity.' '.$related_text).' entity collection (one to many).')
                    ->write(' *')
                    ->write(' * @return '.$table->getCollectionInterface())
                    ->write(' */')
                    ->write('public function get'.$this->columnNameBeautifier(Inflector::pluralize($targetEntity)).$related.'()')
                    ->write('{')
                    ->indent()
                        ->write('return $this->'.$attribute_name.';')
                    ->outdent()
                    ->write('}')
                ;
            } else { // OneToOne
                $writer
                    // setter
                    ->write('/**')
                    ->write(' * Set '.$targetEntity.' entity (one to one).')
                    ->write(' *')
                    ->write(' * @param '.$targetEntityFQCN.' $'.lcfirst($targetEntity))
                    ->write(' * @return '.$table->getNamespace())
                    ->write(' */')
                    ->write('public function set'.$this->columnNameBeautifier($targetEntity).'('.$targetEntityFQCN.' $'.lcfirst($targetEntity).')')
                    ->write('{')
                    ->indent()
                        ->write('$this->'.lcfirst($targetEntity).' = $'.lcfirst($targetEntity).';')
                        ->write('')
                        ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                    // getter
                    ->write('/**')
                    ->write(' * Get '.$targetEntity.' entity (one to one).')
                    ->write(' *')
                    ->write(' * @return '.$targetEntityFQCN)
                    ->write(' */')
                    ->write('public function get'.$this->columnNameBeautifier($targetEntity).'()')
                    ->write('{')
                    ->indent()
                        ->write('return $this->'.lcfirst($targetEntity).';')
                    ->outdent()
                    ->write('}')
                ;
            }
            $writer
                ->write('')
            ;
        }
        // many to one references
        if (null !== $this->local) {
            $unidirectional = ($this->local->parseComment('unidirectional') === 'true');

            $targetEntity = $this->local->getReferencedTable()->getModelName();
            $targetEntityFQCN = $this->local->getReferencedTable()->getModelNameAsFQCN($this->local->getOwningTable()->getEntityNamespace());

            if ($this->local->isManyToOne()) { // is ManyToOne
                $related_text = $this->getManyToManyRelatedName($this->local->getReferencedTable()->getRawTableName(), $this->local->getForeign()->getColumnName(), false);

                $attribute_name = lcfirst($targetEntity);

                if ($this->getParent()->getManyToManyCount($this->local->getReferencedTable()->getRawTableName()) > 1) {
                    /*
                     * use the name of the foreign key if there is more than one column holding a relation from this table to the target table.
                     */
                    $attribute_name = lcfirst($this->local->getParameters()->get('name'));
                }

                $function_name = $this->columnNameBeautifier($attribute_name);

                $writer
                    // setter
                    ->write('/**')
                    ->write(' * Set '.trim($targetEntity.' '.$related_text).' entity (many to one).')
                    ->write(' *')
                    ->write(' * @param '.$targetEntityFQCN.' $'.lcfirst($targetEntity))
                    ->write(' * @return '.$table->getNamespace())
                    ->write(' */')
                    ->writeIf(!$this->isNotNull(), 'public function set'.$function_name.'('.$targetEntityFQCN.' $'.lcfirst($targetEntity).' = null)')
                    ->writeIf($this->isNotNull(), 'public function set'.$function_name.'('.$targetEntityFQCN.' $'.lcfirst($targetEntity).')')
                    ->write('{')
                    ->indent()
                        ->write('$this->'.$attribute_name.' = $'.lcfirst($targetEntity).';')
                        ->write('')
                        ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                    // getter
                    ->write('/**')
                    ->write(' * Get '.trim($targetEntity.' '.$related_text).' entity (many to one).')
                    ->write(' *')
                    ->write(' * @return '.$targetEntityFQCN)
                    ->write(' */')
                    ->write('public function get'.$function_name.'()')
                    ->write('{')
                    ->indent()
                        ->write('return $this->'.$attribute_name.';')
                    ->outdent()
                    ->write('}')
                    ->write('')
                ;
            } else { // OneToOne
                $writer
                    // setter
                    ->write('/**')
                    ->write(' * Set '.$targetEntity.' entity (one to one).')
                    ->write(' *')
                    ->write(' * @param '.$targetEntityFQCN.' $'.lcfirst($targetEntity))
                    ->write(' * @return '.$table->getNamespace())
                    ->write(' */')
                    ->write('public function set'.$this->columnNameBeautifier($targetEntity).'('.$targetEntityFQCN.' $'.lcfirst($targetEntity).' = null)')
                    ->write('{')
                    ->indent()
                        ->writeIf(!$unidirectional, '$'.lcfirst($targetEntity).'->set'.$this->columnNameBeautifier($this->local->getOwningTable()->getModelName()).'($this);')
                        ->write('$this->'.lcfirst($targetEntity).' = $'.lcfirst($targetEntity).';')
                        ->write('')
                        ->write('return $this;')
                    ->outdent()
                    ->write('}')
                    ->write('')
                    // getter
                    ->write('/**')
                    ->write(' * Get '.$targetEntity.' entity (one to one).')
                    ->write(' *')
                    ->write(' * @return '.$targetEntityFQCN)
                    ->write(' */')
                    ->write('public function get'.$this->columnNameBeautifier($targetEntity).'()')
                    ->write('{')
                    ->indent()
                        ->write('return $this->'.lcfirst($targetEntity).';')
                    ->outdent()
                    ->write('}')
                    ->write('')
                ;
            }
        }

        return $this;
    }

    private function getDefaultValueAsPhp()
    {
        $default_value = $this->getDefaultValue();

        if ($default_value !== null) {
            $converter = $this->getDocument()->getFormatter()->getDatatypeConverter();
            $nativeType = $converter->getNativeType($converter->getMappedType($this));

            switch ($nativeType) {
                case 'array':
                    $default_value = is_array($default_value) ? var_export($default_value, true) : null;
                break;
                case 'boolean':
                    $default_value = is_bool($default_value) ? ($default_value ? 'true' : 'false') : null;
                break;
                case 'integer':
                    if (!is_int($default_value) && !(is_string($default_value) && preg_match('/^-?\d+$/', $default_value))) {
                        $default_value = null;
                    }
                break;
                case 'string':
                    $default_value = var_export($default_value, true);
                break;
                case 'float':
                    if (!is_int($default_value) && !is_float($default_value) && !(is_string($default_value) && preg_match('/^-?\d+$/', $default_value))) {
                        $default_value = null;
                    }
                break;
                case 'object':
                case 'datetime':
                default:
                    $default_value = null;
                break;
            }
        }

        return $default_value;
    }
}
