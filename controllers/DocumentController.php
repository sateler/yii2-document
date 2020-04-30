<?php

namespace sateler\document\controllers;

use Yii;
use yii\filters\AccessControl;
use sateler\document\Document;
use yii\web\Response;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\HttpCache;

/**
 * DocumentController implements the CRUD actions for Document model.
 */
class DocumentController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            [
                'class' => HttpCache::className(),
                'only' => ['view'],
                'lastModified' => function ($action, $params) {
                    return Document::find()->where(['id' => Yii::$app->request->get('id')])->max('updated_at');
                },
            ],
        ];
    }
    
    /**
     * Displays a single Document model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);
        $res = Yii::$app->response;
        /* @var $res \yii\web\Response */
        $res->format = Response::FORMAT_RAW;
        $res->setDownloadHeaders($model->name, $model->mime_type, true);
        $res->data = $model->contents;
        $res->send();
    }

    /**
     * Finds the Document model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Document the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Document::findOne($id)) !== null) {
            return $model;
        } else {
            $res = Yii::$app->response;
            $res->format = Response::FORMAT_RAW;
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
