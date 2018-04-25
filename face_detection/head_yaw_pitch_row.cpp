//
// Created by POWER on 2018/4/22.
//
#include <vector>
#include <opencv2/imgproc/imgproc.hpp>
#include <opencv2/highgui/highgui.hpp>
#include <opencv2/opencv.hpp>
#include <dlib/image_processing/frontal_face_detector.h>
#include <dlib/image_processing/render_face_detections.h>

#include "constants.h"
#include "head_yaw_pitch_row.h"

using namespace std;
using namespace dlib;
using namespace cv;

// calculate head posting
std::vector<Dual_Points> head_pose_estimation(std::vector<full_object_detection> &shapes, Mat &img)
{
    std::vector<std::vector<cv::Point2d>> faces;
    // create a 3d model in world coordinate for the head
    std::vector<cv::Point3d> model;
    model.push_back(cv::Point3d(0.0f, 0.0f, 0.0f));
    model.push_back(cv::Point3d(0.0f, -330.0f, -65.0f));
    model.push_back(cv::Point3d(-225.0f, 170.0f, -135.0f));
    model.push_back(cv::Point3d(225.0f, 170.0f, -135.0f));
    model.push_back(cv::Point3d(-150.0f, -150.0f, -125.0f));
    model.push_back(cv::Point3d(150.0f, -150.0f, -125.0f));

    double focal_length = img.cols; //Approximate focal length
    Point2d center = cv::Point2d(img.cols / 2, img.rows / 2);
    cv::Mat camera_matrix;
    camera_matrix = (cv::Mat_<double>(3, 3) << focal_length, 0, center.x, 0, focal_length, center.y, 0, 0, 1);
    cv::Mat dist_coeffs = cv::Mat::zeros(4, 1, cv::DataType<double>::type); //assuming no lens distortion

    std::vector<Dual_Points> points;

    for (int i = 0; i < shapes.size(); i++)
    {
        faces.push_back(std::vector<cv::Point2d>(6));

        faces[i][0] = cv::Point2d(shapes[i].part(30).x(), shapes[i].part(30).y()); //Nose tip, 31
        faces[i][1] = cv::Point2d(shapes[i].part(8).x(), shapes[i].part(8).y());   //Chin, 9
        faces[i][2] = cv::Point2d(shapes[i].part(36).x(), shapes[i].part(36).y()); //Left eye left corner, 37
        faces[i][3] = cv::Point2d(shapes[i].part(45).x(), shapes[i].part(45).y()); //Right eye right corner, 46
        faces[i][4] = cv::Point2d(shapes[i].part(48).x(), shapes[i].part(48).y()); //Left mouth corner, 49
        faces[i][5] = cv::Point2d(shapes[i].part(54).x(), shapes[i].part(54).y()); //Right mouth corner, 55

        cv::Mat rotation_vector;
        cv::Mat translation_vector;

        cv::solvePnP(model, faces[i], camera_matrix, dist_coeffs, rotation_vector, translation_vector);

        std::vector<Point3d> nose_end_point3D;
        std::vector<Point2d> nose_end_point2D;
        nose_end_point3D.push_back(Point3d(0, 0, 500.0));

        projectPoints(nose_end_point3D, rotation_vector, translation_vector, camera_matrix, dist_coeffs, nose_end_point2D);

        //cv::line(img, faces[i][0], nose_end_point2D[0], cv::Scalar(0, 255, 0), 2);
        Dual_Points p;
        p.point1 = faces[i][0];
        p.point2 = nose_end_point2D[0];
        points.push_back(p);
/*
        cout << "Rotation Vector " << endl
             << rotation_vector << endl;
        cout << "Translation Vector " << endl
             << translation_vector << endl;
        cout << nose_end_point2D << endl;
*/
    }
    return points;
}


// draw a line indicates the head posting
void draw_head_posting(Mat &img, const std::vector<Dual_Points> &p)
{
    for (int i = 0; i < p.size(); i++)
    {
        cv::line(img, p[i].point1, p[i].point2, cv::Scalar(0, 255, 0), 2);
    }
}
