<?php

/*
 * This file is part of Hiject.
 *
 * Copyright (C) 2016 Hiject Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hiject\Model;

use Hiject\Core\Base;

/**
 * Category model
 */
class CategoryModel extends Base
{
    /**
     * SQL table name
     *
     * @var string
     */
    const TABLE = 'project_has_categories';

    /**
     * Return true if a category exists for a given project
     *
     * @access public
     * @param  integer   $category_id    Category id
     * @return boolean
     */
    public function exists($category_id)
    {
        return $this->db->table(self::TABLE)->eq('id', $category_id)->exists();
    }

    /**
     * Get a category by the id
     *
     * @access public
     * @param  integer   $category_id    Category id
     * @return array
     */
    public function getById($category_id)
    {
        return $this->db->table(self::TABLE)->eq('id', $category_id)->findOne();
    }

    /**
     * Get the category name by the id
     *
     * @access public
     * @param  integer   $category_id    Category id
     * @return string
     */
    public function getNameById($category_id)
    {
        return $this->db->table(self::TABLE)->eq('id', $category_id)->findOneColumn('name') ?: '';
    }

    /**
     * Get the projectId by the category id
     *
     * @access public
     * @param  integer   $category_id    Category id
     * @return integer
     */
    public function getProjectId($category_id)
    {
        return $this->db->table(self::TABLE)->eq('id', $category_id)->findOneColumn('project_id') ?: 0;
    }

    /**
     * Get the first category id for a given project
     *
     * @access public
     * @param  integer  $project_id   Project id
     * @return integer
     */
    public function getFirstCategoryId($project_id)
    {
        return $this->db->table(self::TABLE)->eq('project_id', $project_id)->asc('position')->findOneColumn('id');
    }

    /**
     * Get the last category id for a given project
     *
     * @access public
     * @param  integer  $project_id   Project id
     * @return integer
     */
    public function getLastCategoryId($project_id)
    {
        return $this->db->table(self::TABLE)->eq('project_id', $project_id)->desc('position')->findOneColumn('id');
    }

    /**
     * Get the position of the last category for a given project
     *
     * @access public
     * @param  integer  $project_id   Project id
     * @return integer
     */
    public function getLastCategoryPosition($project_id)
    {
        return (int) $this->db
                        ->table(self::TABLE)
                        ->eq('project_id', $project_id)
                        ->desc('position')
                        ->findOneColumn('position');
    }

    /**
     * Get a category id by the category name and project id
     *
     * @access public
     * @param  integer   $project_id      Project id
     * @param  string    $category_name   Category name
     * @return integer
     */
    public function getIdByName($project_id, $category_name)
    {
        return (int) $this->db->table(self::TABLE)
                        ->eq('project_id', $project_id)
                        ->eq('name', $category_name)
                        ->findOneColumn('id');
    }

    /**
     * Return the list of all categories
     *
     * @access public
     * @param  integer   $project_id    Project id
     * @param  bool      $prepend_none  If true, prepend to the list the value 'None'
     * @param  bool      $prepend_all   If true, prepend to the list the value 'All'
     * @return array
     */
    public function getList($project_id, $prepend_none = true, $prepend_all = false)
    {
        $listing = $this->db->hashtable(self::TABLE)
            ->eq('project_id', $project_id)
            ->asc('position')
            ->getAll('id', 'name');

        $prepend = [];

        if ($prepend_all) {
            $prepend[-1] = t('All categories');
        }

        if ($prepend_none) {
            $prepend[0] = t('No category');
        }

        return $prepend + $listing;
    }

    /**
     * Return all categories for a given project
     *
     * @access public
     * @param  integer   $project_id    Project id
     * @return array
     */
    public function getAll($project_id)
    {
        return $this->db->table(self::TABLE)
            ->eq('project_id', $project_id)
            ->asc('position')
            ->findAll();
    }

    /**
     * Create default categories during project creation (transaction already started in Project::create())
     *
     * @access public
     * @param  integer  $project_id
     * @return boolean
     */
    public function createDefaultCategories($project_id)
    {
        $results = [];
        $categories = explode(',', $this->configModel->get('project_categories'));
        $position = 1;

        foreach ($categories as $category) {
            $category = trim($category);

            if (! empty($category)) {
                $results[] = $this->db->table(self::TABLE)->insert([
                    'project_id' => $project_id,
                    'name' => $category,
                    'position' => $position,
                ]);

                $position++;
            }
        }

        return in_array(false, $results, true);
    }

    /**
     * Create a category (run inside a transaction)
     *
     * @access public
     * @param  array    $values    Form values
     * @return bool|integer
     */
    public function create(array $values)
    {
        $values['position'] = isset($values['position']) ? $values['position'] : $this->getLastCategoryPosition($values['project_id']) + 1;

        return $this->db->table(self::TABLE)->persist($values);
    }

    /**
     * Update a category
     *
     * @access public
     * @param  array    $values    Form values
     * @return bool
     */
    public function update(array $values)
    {
        return $this->db->table(self::TABLE)->eq('id', $values['id'])->save($values);
    }

    /**
     * Remove a category
     *
     * @access public
     * @param  integer   $category_id    Category id
     * @return bool
     */
    public function remove($category_id)
    {
        $this->db->startTransaction();

        $this->db->table(TaskModel::TABLE)->eq('category_id', $category_id)->update(['category_id' => 0]);

        if (! $this->db->table(self::TABLE)->eq('id', $category_id)->remove()) {
            $this->db->cancelTransaction();
            return false;
        }

        $this->db->closeTransaction();

        return true;
    }

    /**
     * Duplicate categories from a project to another one, must be executed inside a transaction
     *
     * @author Antonio Rabelo
     * @param  integer    $src_project_id        Source project id
     * @param  integer    $dst_project_id        Destination project id
     * @return boolean
     */
    public function duplicate($src_project_id, $dst_project_id)
    {
        $categories = $this->db
            ->table(self::TABLE)
            ->columns('name', 'description', 'position')
            ->eq('project_id', $src_project_id)
            ->asc('position')
            ->findAll();

        foreach ($categories as $category) {
            $category['project_id'] = $dst_project_id;

            if (! $this->db->table(self::TABLE)->save($category)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Change category position
     *
     * @access public
     * @param  integer  $project_id
     * @param  integer  $category_id
     * @param  integer  $position
     * @return boolean
     */
    public function changePosition($project_id, $category_id, $position)
    {
        if ($position < 1 || $position > $this->db->table(self::TABLE)->eq('project_id', $project_id)->count()) {
            return false;
        }

        $category_ids = $this->db->table(self::TABLE)->eq('project_id', $project_id)->neq('id', $category_id)->asc('position')->findAllByColumn('id');
        $offset = 1;
        $results = [];

        foreach ($category_ids as $current_category_id) {
            if ($offset == $position) {
                $offset++;
            }

            $results[] = $this->db->table(self::TABLE)->eq('id', $current_category_id)->update(['position' => $offset]);
            $offset++;
        }

        $results[] = $this->db->table(self::TABLE)->eq('id', $category_id)->update(['position' => $position]);

        return !in_array(false, $results, true);
    }
}
