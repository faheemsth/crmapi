<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\ModuleType;
use App\Models\PermissionType;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function getPermissionPluck(Request $request)
    {
        $permissions = Permission::with(['moduleType', 'permissionType'])
                        ->pluck('name', 'id')
                        ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $permissions
        ], 200);
    }

    public function getPermissions()
    {
        $permissions = Permission::with([
                            'created_by:id,name',
                            'moduleType:id,name',
                            'permissionType:id,name'
                        ])->get();

        return response()->json([
            'status' => 'success',
            'data' => $permissions
        ], 200);
    }

    public function addPermission(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required|string',
                'moduleTypeID' => 'required|exists:module_types,id',
                'permissionTypeID' => 'required|exists:permission_types,id'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $permission = Permission::create([
            'name' => $request->name,
            'module_type_id' => $request->moduleTypeID,
            'permission_type_id' => $request->permissionTypeID,
            'created_by' => \Auth::id()
        ]);

        addLogActivity([
            'type' => 'success',
            'note' => json_encode([
                'title' => $permission->name . " permission created",
                'message' => $permission->name . " permission created",
            ]),
            'module_id' => $permission->id,
            'module_type' => 'permission',
            'notification_type' => 'Permission Created',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Permission successfully created.'),
            'data' => $permission->load(['moduleType', 'permissionType'])
        ], 201);
    }

    public function updatePermission(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            [
                'id' => 'required|exists:permissions,id',
              //  'name' => 'required|string',
                'moduleTypeID' => 'required|exists:module_types,id',
                'permissionTypeID' => 'required|exists:permission_types,id'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()
            ], 422);
        }

        $permission = Permission::where('id', $request->id)->first();

        if (!$permission) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission not found.')
            ], 404);
        }

        $originalData = $permission->toArray();

        $permission->update([
         //   'name' => $request->name,
            'module_type_id' => $request->moduleTypeID,
            'permission_type_id' => $request->permissionTypeID,
            'created_by' => \Auth::id()
        ]);

        $changes = [];
        $updatedFields = [];

        foreach ($originalData as $field => $oldValue) {
            if (in_array($field, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($permission->$field != $oldValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $permission->$field
                ];
                $updatedFields[] = $field;
            }
        }

        if (!empty($changes)) {
            addLogActivity([
                'type' => 'info',
                'note' => json_encode([
                    'title' => $permission->name . " permission updated",
                    'message' => 'Fields updated: ' . implode(', ', $updatedFields),
                    'changes' => $changes
                ]),
                'module_id' => $permission->id,
                'module_type' => 'permission',
                'notification_type' => 'Permission Updated',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => __('Permission successfully updated.'),
            'data' => $permission->load(['moduleType', 'permissionType'])
        ], 200);
    }

    public function deletePermission(Request $request)
    {
        $validator = \Validator::make(
            $request->all(),
            ['id' => 'required|exists:permissions,id']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $permission = Permission::where('id', $request->id)->first();

        if (!$permission) {
            return response()->json([
                'status' => 'error',
                'message' => __('Permission not found.')
            ], 404);
        }

        $permissionName = $permission->name;
        $permissionId = $permission->id;

        $permission->delete();

        addLogActivity([
            'type' => 'warning',
            'note' => json_encode([
                'title' => $permissionName . " permission deleted",
                'message' => $permissionName . " permission deleted"
            ]),
            'module_id' => $permissionId,
            'module_type' => 'permission',
            'notification_type' => 'Permission Deleted',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __('Permission successfully deleted.')
        ], 200);
    }
}