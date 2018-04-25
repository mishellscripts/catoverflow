
#include <dlib/threads.h>
#include <dlib/image_processing/frontal_face_detector.h>
#include <dlib/image_processing/render_face_detections.h>
#include <dlib/image_processing.h>
#include <dlib/opencv/cv_image.h>
#include <dlib/gui_widgets.h>
#include <dlib/image_io.h>
#include <dlib/dir_nav.h>
#include <iostream>
#include <vector>
#include <sstream>
#include <opencv2/imgproc/imgproc.hpp>
#include <opencv2/highgui/highgui.hpp>
#include <opencv2/opencv.hpp>
#include <dlib/opencv.h>
#include <time.h>
#include <algorithm>


#include "eyeLike.h"
#include "constants.h"
#include "head_yaw_pitch_row.h"
#include "delaunay_triangle.h"

// include different header files base on different OS
#if defined _MSC_VER
#include <direct.h>
#include <CommCtrl.h>

#define file file
#elif defined __GNUC__
#include <sys/types.h>
#include <sys/stat.h>
#endif


using namespace dlib;
using namespace cv;
using namespace std;

//------------------------------------------------------------------------------------------------
// function prototypes
void make_directory(const string &output_dir);

std::vector<Mat> load_imgs(const string &input_dir);

void write_imgs(const std::vector<Mat> &images,
                const string &output_dir,
                const string &video_id);

void run(std::vector<Mat> &imgs);

void process_image(cv::Mat &img, dlib::frontal_face_detector &detector, dlib::shape_predictor &sp);

cv::Rect dlib_rect_to_opencv_rect(const dlib::rectangle &rect);
//------------------------------------------------------------------------------------------------

//================================================================================================
// Global Valuable
std::vector<dlib::file> files;


int main(int argc, char **argv)
{
    // check the command line argument
    if (argc != 4)
    {
        cout << "Call this program like this:" << endl;
        cout << "Linux: ./face_detection ./video_frames ./output_dir video_id" << endl;
        cout << "Windows: face_detection.exe .\\video_frames .\\output_dir video_id" << endl;
        cout << "\nYou can get the shape_predictor_68_face_landmarks.dat file from:\n";
        cout << "http://dlib.net/files/shape_predictor_68_face_landmarks.dat.bz2" << endl;
        return 0;
    }

    make_directory(argv[2]);    // make the output dir

    // load images to memory, if on images are read exit the program
    std::vector<Mat> images = load_imgs(argv[1]);
    if (images.size() == 0)
    {
        return 1;
    }

    // start processing
    run(images);

    // store images to disk
    write_imgs(images, argv[2], argv[3]);

    return 0;
}

// create a folder to store processed images
void make_directory(const string &output_dir)
{
#if defined _MSC_VER
        _mkdir(output_dir.c_str());
#elif defind __GNUC__
        mkdir(output_dir.c_str(), S_IRWXU | S_IRWXG | S_IROTH | S_IXOTH);
#endif
}

// input_dir: folder that stores all original images
// return: vector of Mat (original images)
// uses dlib's get_files_in_directory_tree to get all the images files and load into a vector<Mat>
std::vector<Mat> load_imgs(const string &input_dir)
{
    files = get_files_in_directory_tree(input_dir, match_ending(IMAGE_TYPE), 0);

    std::sort(files.begin(), files.end());

    if (files.size() == 0)
    {
        cout << "No images found in " << input_dir << endl;
        return std::vector<Mat>(0);
    }
    std::vector<Mat> imgs;
    for (int i = 0; i < files.size(); i++)
    {
        imgs.push_back(imread(files[i].full_name()));
        //cout << files[i].full_name() << endl;
    }
    return imgs;

}

// images: processed images
// stores all the process images into the ourput_dir
void write_imgs(const std::vector<Mat> &images,
                const string &output_dir,
                const string &video_id)
{
    try {
        for (int i = 0; i < images.size(); i++)
        {
            imwrite(output_dir + "/" + video_id + "." + to_string(i) + IMAGE_TYPE, images[i]);
        }
    } catch (Exception &e) {
        cout << e.what() << endl;
    }

}

// imgs: original images as input, and will be modify after processing
// predictor: pretrain data from dlib that gets 68 face landmarks
// this function processes original images
void run(std::vector<Mat> &imgs)
{
    //unsigned long num_thread_test = 1;
    //cout << "enter number of threads: ";
    //cin >> num_thread_test;
    thread_pool tp(NUM_THREAD);
    //thread_pool tp(num_thread_test);

    time_t start = time(nullptr);   // get the initial time for calculate the time for processing

    // create face detector and shape detector for each thread
    std::vector<dlib::future<frontal_face_detector>> f_detectors(NUM_THREAD);
    std::vector<dlib::future<shape_predictor>> f_sp(NUM_THREAD);
    for (int i = 0; i < NUM_THREAD; i++)
    {
        f_detectors[i] = get_frontal_face_detector();
        try {
            deserialize(FACE_LANDMARKS) >> f_sp[i];
        } catch (Exception &e) {
            cout << e.what() << endl;
        }
    }

    // pack images into future objects for multithreading
    std::vector<dlib::future<Mat>> f_imgs;
    for (const auto &img : imgs)
    {
        f_imgs.push_back(dlib::future<Mat>(img));
    }
    // main loop
    // loop through all the images in the memory and process it one by one in order
    for (int i = 0; i < f_imgs.size(); i++)
    {
        unsigned int index = (i + NUM_THREAD) % NUM_THREAD;
        tp.add_task(&process_image, f_imgs[i], f_detectors[index], f_sp[index]);
        cout << "start frame: " << i << endl;
    }
    tp.wait_for_all_tasks();
    // output the total time usage
    cout << "time: " << time(nullptr) - start << endl;
}


void process_image(Mat &img, frontal_face_detector &detector, shape_predictor &sp)
{
    //frontal_face_detector detector = get_frontal_face_detector();
    //shape_predictor sp;
    //deserialize("shape_predictor_68_face_landmarks.dat") >> sp;

    // conver opencv's Mat (data type for image) to dlib's image type
    cv_image<bgr_pixel> cimg(img);

    // uses dlib's face detection find the faces in an image
    // stores the faces areas in a vector of rectangle
    std::vector<dlib::rectangle> dets = detector(cimg);     //faces

    // shapes stores 68 face landmarks for each face in an image
    std::vector<full_object_detection> shapes;
    std::vector<cv::Rect> cv_faces;

    for (auto& det : dets)
    {
        full_object_detection shape = sp(cimg, det);
        shapes.push_back(shape);
        // convert the dlib rectangle of faces' area to opencv rectangle for eye_detection
        cv_faces.push_back(dlib_rect_to_opencv_rect(det));
    }

    // calling eye_detection to detect they eyes in image
    // Dual_Points contains left eye (point1) and right eye (point2)
    std::vector<Dual_Points> eyes = eye_pupils(img, cv_faces, shapes);

    // detect head posting
    // gets two points for the facing direction
    /***may change to use OpenFace later***/
    std::vector<Dual_Points> heads = head_pose_estimation(shapes, img);

    // draw to the image
    draw_delaunay_triangles(shapes, img);
    draw_eye_center(img, eyes);
    draw_head_posting(img, heads);
}

// convert dlib rectangle to opencv Rect
cv::Rect dlib_rect_to_opencv_rect(const dlib::rectangle &rect)
{
    return cv::Rect(cv::Point2i(rect.left(), rect.top()), cv::Point2i(rect.right() + 1, rect.bottom() + 1));
}





