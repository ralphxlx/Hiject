<?php

/*
 * This file is part of Hiject.
 *
 * Copyright (C) 2016 Hiject Team
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Hiject\Model\ProjectModel;
use Hiject\Model\SubtaskModel;
use Hiject\Model\SubtaskStatusModel;
use Hiject\Model\TaskCreationModel;

require_once __DIR__.'/../Base.php';

class SubtaskStatusModelTest extends Base
{
    public function testToggleStatusWithoutSession()
    {
        $taskCreationModel = new TaskCreationModel($this->container);
        $subtaskModel = new SubtaskModel($this->container);
        $subtaskStatusModel = new SubtaskStatusModel($this->container);
        $projectModel = new ProjectModel($this->container);

        $this->assertEquals(1, $projectModel->create(['name' => 'test1']));
        $this->assertEquals(1, $taskCreationModel->create(['title' => 'test 1', 'project_id' => 1]));

        $this->assertEquals(1, $subtaskModel->create(['title' => 'subtask #1', 'task_id' => 1]));

        $subtask = $subtaskModel->getById(1);
        $this->assertNotEmpty($subtask);
        $this->assertEquals(SubtaskModel::STATUS_TODO, $subtask['status']);
        $this->assertEquals(0, $subtask['user_id']);
        $this->assertEquals(1, $subtask['task_id']);

        $this->assertEquals(SubtaskModel::STATUS_INPROGRESS, $subtaskStatusModel->toggleStatus(1));

        $subtask = $subtaskModel->getById(1);
        $this->assertNotEmpty($subtask);
        $this->assertEquals(SubtaskModel::STATUS_INPROGRESS, $subtask['status']);
        $this->assertEquals(0, $subtask['user_id']);
        $this->assertEquals(1, $subtask['task_id']);

        $this->assertEquals(SubtaskModel::STATUS_DONE, $subtaskStatusModel->toggleStatus(1));

        $subtask = $subtaskModel->getById(1);
        $this->assertNotEmpty($subtask);
        $this->assertEquals(SubtaskModel::STATUS_DONE, $subtask['status']);
        $this->assertEquals(0, $subtask['user_id']);
        $this->assertEquals(1, $subtask['task_id']);

        $this->assertEquals(SubtaskModel::STATUS_TODO, $subtaskStatusModel->toggleStatus(1));

        $subtask = $subtaskModel->getById(1);
        $this->assertNotEmpty($subtask);
        $this->assertEquals(SubtaskModel::STATUS_TODO, $subtask['status']);
        $this->assertEquals(0, $subtask['user_id']);
        $this->assertEquals(1, $subtask['task_id']);
    }

    public function testToggleStatusWithSession()
    {
        $taskCreationModel = new TaskCreationModel($this->container);
        $subtaskModel = new SubtaskModel($this->container);
        $projectModel = new ProjectModel($this->container);
        $subtaskStatusModel = new SubtaskStatusModel($this->container);

        $this->assertEquals(1, $projectModel->create(['name' => 'test1']));
        $this->assertEquals(1, $taskCreationModel->create(['title' => 'test 1', 'project_id' => 1]));

        $this->assertEquals(1, $subtaskModel->create(['title' => 'subtask #1', 'task_id' => 1]));

        $subtask = $subtaskModel->getById(1);
        $this->assertNotEmpty($subtask);
        $this->assertEquals(SubtaskModel::STATUS_TODO, $subtask['status']);
        $this->assertEquals(0, $subtask['user_id']);
        $this->assertEquals(1, $subtask['task_id']);

        // Set the current logged user
        $this->container['sessionStorage']->user = ['id' => 1];

        $this->assertEquals(SubtaskModel::STATUS_INPROGRESS, $subtaskStatusModel->toggleStatus(1));

        $subtask = $subtaskModel->getById(1);
        $this->assertNotEmpty($subtask);
        $this->assertEquals(SubtaskModel::STATUS_INPROGRESS, $subtask['status']);
        $this->assertEquals(1, $subtask['user_id']);
        $this->assertEquals(1, $subtask['task_id']);

        $this->assertEquals(SubtaskModel::STATUS_DONE, $subtaskStatusModel->toggleStatus(1));

        $subtask = $subtaskModel->getById(1);
        $this->assertNotEmpty($subtask);
        $this->assertEquals(SubtaskModel::STATUS_DONE, $subtask['status']);
        $this->assertEquals(1, $subtask['user_id']);
        $this->assertEquals(1, $subtask['task_id']);

        $this->assertEquals(SubtaskModel::STATUS_TODO, $subtaskStatusModel->toggleStatus(1));

        $subtask = $subtaskModel->getById(1);
        $this->assertNotEmpty($subtask);
        $this->assertEquals(SubtaskModel::STATUS_TODO, $subtask['status']);
        $this->assertEquals(1, $subtask['user_id']);
        $this->assertEquals(1, $subtask['task_id']);
    }

    public function testCloseAll()
    {
        $taskCreationModel = new TaskCreationModel($this->container);
        $subtaskModel = new SubtaskModel($this->container);
        $projectModel = new ProjectModel($this->container);
        $subtaskStatusModel = new SubtaskStatusModel($this->container);

        $this->assertEquals(1, $projectModel->create(['name' => 'test1']));
        $this->assertEquals(1, $taskCreationModel->create(['title' => 'test 1', 'project_id' => 1]));

        $this->assertEquals(1, $subtaskModel->create(['title' => 'subtask #1', 'task_id' => 1]));
        $this->assertEquals(2, $subtaskModel->create(['title' => 'subtask #2', 'task_id' => 1]));

        $this->assertTrue($subtaskStatusModel->closeAll(1));

        $subtasks = $subtaskModel->getAll(1);
        $this->assertNotEmpty($subtasks);

        foreach ($subtasks as $subtask) {
            $this->assertEquals(SubtaskModel::STATUS_DONE, $subtask['status']);
        }
    }
}
